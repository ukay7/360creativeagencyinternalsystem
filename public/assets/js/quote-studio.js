(function () {
    'use strict';
    var root = document.getElementById('quote-studio');
    if (!root) return;

    var data = JSON.parse(document.getElementById('quote-studio-data').textContent || '{}');
    var dictionaries = JSON.parse(document.getElementById('quote-studio-i18n').textContent || '{}');
    var form = document.getElementById('quote-builder-form');
    var proposal = data.proposal || null;
    var step = 1;
    var locale = root.dataset.locale || 'en';
    var tier = (proposal && proposal.selected_tier) || 'basic';
    var supportCode = (proposal && proposal.support_plan) || 'none';
    var supportPackageId = Number((proposal && proposal.support_package_id) || 0);
    var recommendationRules = data.recommendation || {basic_max:7, medium_max:14, max_score:20, presets:{basic:0,medium:1,pro:2}};
    var membershipDuration = Number((proposal && proposal.membership_duration_months) || 3);
    var membershipTermMode = [3,6,12].indexOf(membershipDuration) >= 0 ? String(membershipDuration) : 'custom';
    var activeItems = [];
    var supportActiveItems = [];
    var originalItems = {};
    (data.initialItems || []).forEach(function (item) { originalItems[String(item.service_id)] = item; });
    var originalSupportItems = {};
    (data.initialSupportItems || []).forEach(function (item) { originalSupportItems[String(item.service_id)] = item; });
    var workItemSelections = {};
    var supportStateByPackage = {};
    var scopeEditingRow = null;

    function clone(value) { return JSON.parse(JSON.stringify(value || [])); }
    function workSelectionKey(serviceId) { return String(tier) + ':' + String(serviceId); }
    function supportWorkSelectionKey(packageId,serviceId) { return 'support:' + String(packageId) + ':' + String(serviceId); }
    function quoteWorkSelection(packageItems,savedItems) {
        if (!savedItems || !savedItems.length) return clone(packageItems || []);
        var savedByTemplate={};savedItems.forEach(function(item){savedByTemplate[String(item.service_work_item_id||item.id)]=item;});
        return (packageItems||[]).map(function(item){var saved=savedByTemplate[String(item.id)];return saved?Object.assign({},clone(item),clone(saved),{service_work_item_id:item.id,included:1}):Object.assign({},clone(item),{service_work_item_id:item.id,included:0});});
    }

    function money(value) {
        return new Intl.NumberFormat(locale === 'en' ? 'en-CA' : locale, {style:'currency', currency:'CAD', minimumFractionDigits:2}).format(Number(value || 0));
    }
    function esc(value) {
        var node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML;
    }
    function localized(record, field) {
        var suffix = locale === 'en' ? '' : '_' + locale;
        return record[field + suffix] || record[field] || '';
    }
    function dictionary(key) { return (dictionaries[locale] && dictionaries[locale][key]) || (dictionaries.en && dictionaries.en[key]) || key; }

    var relationshipSelect = document.getElementById('existing-relationship');
    var relationshipSearch = document.getElementById('relationship-search');
    var relationshipList = document.getElementById('relationship-options');
    var relationshipToggle = document.getElementById('relationship-toggle');
    var relationshipActiveIndex = -1;

    function relationshipChoices() { return Array.prototype.slice.call(relationshipSelect.options); }
    function selectedRelationshipText() {
        var option = relationshipSelect.options[relationshipSelect.selectedIndex];
        return option ? option.textContent.trim() : dictionary('no_existing_client');
    }
    function closeRelationshipPicker() {
        relationshipList.hidden = true;
        relationshipSearch.setAttribute('aria-expanded', 'false');
        relationshipToggle.classList.remove('open');
        relationshipActiveIndex = -1;
    }
    function visibleRelationshipButtons() { return Array.prototype.slice.call(relationshipList.querySelectorAll('[data-relationship-value]')); }
    function focusRelationshipOption(index) {
        var buttons = visibleRelationshipButtons();
        if (!buttons.length) return;
        relationshipActiveIndex = Math.max(0, Math.min(index, buttons.length - 1));
        buttons.forEach(function (button, buttonIndex) { button.classList.toggle('focused', buttonIndex === relationshipActiveIndex); });
        buttons[relationshipActiveIndex].scrollIntoView({block:'nearest'});
    }
    function renderRelationshipOptions(query) {
        var normalizedQuery = String(query || '').trim().toLocaleLowerCase();
        var matches = relationshipChoices().filter(function (option) {
            var searchText = (option.textContent + ' ' + (option.dataset.search || '')).toLocaleLowerCase();
            return !normalizedQuery || searchText.indexOf(normalizedQuery) !== -1;
        });
        relationshipList.innerHTML = '';
        matches.forEach(function (option) {
            var button = document.createElement('button');
            button.type = 'button';
            button.dataset.relationshipValue = option.value;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', option.value === relationshipSelect.value ? 'true' : 'false');
            button.className = 'quote-relationship-option' + (option.value === relationshipSelect.value ? ' selected' : '');
            button.innerHTML = '<span>' + esc(option.textContent.trim()) + '</span><i class="fal fa-check" aria-hidden="true"></i>';
            button.addEventListener('click', function () { chooseRelationship(option.value); });
            relationshipList.appendChild(button);
        });
        if (!matches.length) {
            var empty = document.createElement('p');
            empty.className = 'quote-relationship-empty';
            empty.textContent = dictionary('no_relationship_results');
            relationshipList.appendChild(empty);
        }
        relationshipActiveIndex = -1;
    }
    function openRelationshipPicker(showAll) {
        if (showAll) relationshipSearch.value = '';
        renderRelationshipOptions(relationshipSearch.value);
        relationshipList.hidden = false;
        relationshipSearch.setAttribute('aria-expanded', 'true');
        relationshipToggle.classList.add('open');
    }
    function syncRelationshipPicker() {
        relationshipSearch.placeholder = dictionary('search_relationship');
        relationshipSearch.value = selectedRelationshipText();
        if (!relationshipList.hidden) renderRelationshipOptions('');
    }
    function chooseRelationship(value) {
        relationshipSelect.value = value;
        relationshipSelect.dispatchEvent(new Event('change', {bubbles:true}));
        relationshipSearch.focus();
        closeRelationshipPicker();
        syncRelationshipPicker();
        relationshipSearch.select();
    }
    function clearClientDetails() {
        ['contact_name','business_name','contact_email','contact_phone','website','internal_notes'].forEach(function (name) {
            if (form.elements[name]) form.elements[name].value = '';
        });
        form.elements.business_stage.value = '';
        form.elements.years_operating.value = '';
    }

    function captureServiceState() {
        var state = {};
        document.querySelectorAll('#quote-service-rows tr').forEach(function (row) {
            if (!row._packageItem) return;
            var discount = Math.min(100, Math.max(0, Number(row.querySelector('.quote-item-discount').value || 0)));
            var customText = row.querySelector('.quote-item-custom').value.trim();
            var custom = customText === '' ? null : Math.max(0, Number(customText || 0));
            var list = Number(row._packageItem.unit_price || 0);
            state[String(row._packageItem.service_id)] = {
                enabled: row.querySelector('.quote-item-enabled').checked,
                discount_percent: discount,
                custom_price: custom,
                final_price: custom === null ? Math.round(list * (1 - discount / 100) * 100) / 100 : custom,
                work_items: clone(row._packageItem.work_items || [])
            };
        });
        return state;
    }

    function applyLanguage(useOriginal) {
        var preservedItems = useOriginal ? null : captureServiceState();
        root.setAttribute('dir', locale === 'en' ? 'ltr' : 'rtl');
        root.dataset.locale = locale;
        document.getElementById('quote-locale-input').value = locale;
        root.querySelectorAll('[data-i18n]').forEach(function (node) {
            var key = node.dataset.i18n; if (dictionaries[locale] && dictionaries[locale][key]) node.textContent = dictionaries[locale][key];
        });
        root.querySelectorAll('.assessment-label').forEach(function (node) { node.textContent = node.dataset[locale] || node.dataset.en; });
        root.querySelectorAll('.assessment-answer option').forEach(function (option) { option.textContent = option.dataset[locale] || option.dataset.en; });
        syncRelationshipPicker();
        renderWeightList(); renderServices(Boolean(useOriginal), preservedItems); renderPlans(); updateReview();
    }

    function currentPackage() { return (data.packages || []).find(function (item) { return item.tier === tier; }) || (data.packages || [])[0]; }
    function readRows() {
        var rows = [];
        document.querySelectorAll('#quote-service-rows tr').forEach(function (row) {
            var packageItem = row._packageItem; if (!packageItem || !row.querySelector('.quote-item-enabled').checked) return;
            var list = Number(packageItem.unit_price || 0);
            var discount = Math.min(100, Math.max(0, Number(row.querySelector('.quote-item-discount').value || 0)));
            var customInput = row.querySelector('.quote-item-custom');
            var custom = customInput.value.trim() === '' ? null : Math.max(0, Number(customInput.value || 0));
            var finalPrice = custom === null ? Math.round(list * (1 - discount / 100) * 100) / 100 : custom;
            rows.push({service_id:Number(packageItem.service_id), list_price:list, discount_percent:discount, custom_price:custom, final_price:finalPrice, description:packageItem.description || packageItem.service_description || '', description_ar:packageItem.description_ar || packageItem.service_description_ar || '', description_he:packageItem.description_he || packageItem.service_description_he || '', work_items:clone(packageItem.work_items||[])});
        });
        activeItems = rows;
        return rows;
    }

    function renderServices(useOriginal, preservedItems) {
        var pack = currentPackage(); if (!pack) return;
        document.getElementById('selected-tier').value = tier;
        document.getElementById('tier-override').value = tier;
        root.querySelectorAll('[data-tier]').forEach(function (button) { button.classList.toggle('active', button.dataset.tier === tier); });
        var tbody = document.getElementById('quote-service-rows');
        tbody.innerHTML = '';
        (pack.items || []).forEach(function (item, index) {
            var original = useOriginal ? originalItems[String(item.service_id)] : null;
            var preserved = preservedItems ? preservedItems[String(item.service_id)] : null;
            var selectionKey = workSelectionKey(item.service_id);
            if (preserved) {
                workItemSelections[selectionKey] = clone(preserved.work_items || []);
            } else if (!workItemSelections[selectionKey]) {
                workItemSelections[selectionKey] = quoteWorkSelection(item.work_items || [], original && original.work_items);
            }
            var viewItem = clone(item); viewItem.work_items = workItemSelections[selectionKey];
            var enabled = preserved ? Boolean(preserved.enabled) : (original ? true : Number(item.included) === 1);
            var discount = preserved ? Number(preserved.discount_percent || 0) : (original ? Number(original.discount_percent || 0) : 0);
            var originalFinal = original ? Number(original.total || 0) : null;
            var list = Number(item.unit_price || 0);
            var expectedDiscounted = Math.round(list * (1 - discount / 100) * 100) / 100;
            var custom = preserved ? (preserved.custom_price === null ? '' : Number(preserved.custom_price)) : (original && Math.abs(originalFinal - expectedDiscounted) > 0.009 ? originalFinal : '');
            var displayedFinal = preserved ? Number(preserved.final_price || 0) : (original ? originalFinal : list);
            var title = localized(item, 'name');
            var description = localized(item, 'description') || localized(item, 'service_description');
            var tr = document.createElement('tr'); tr._packageItem = viewItem;
            tr.innerHTML = '<td><label class="quote-check"><input class="quote-item-enabled" type="checkbox" '+(enabled?'checked':'')+'><span></span></label></td>'+
                '<td><span class="quote-row-number">'+(index+1)+'</span></td>'+
                '<td><div class="quote-service-title"><strong>'+esc(title)+'</strong><button type="button" class="quote-scope-help" data-scope-help title="Edit quote items" aria-label="Edit quote items"><i class="fal fa-pencil"></i></button></div><small><b>'+esc(item.category || '')+'</b> · '+esc(description)+'<span class="quote-work-summary">'+((viewItem.work_items||[]).length?' · '+(viewItem.work_items||[]).filter(function(work){return Number(work.included)===1;}).length+' quote items':'')+'</span></small></td>'+
                '<td class="quote-list-price">'+money(list)+'</td>'+
                '<td><input class="quote-price-input quote-item-discount" type="number" min="0" max="100" step="0.01" value="'+esc(discount)+'"></td>'+
                '<td><input class="quote-price-input quote-item-custom" type="number" min="0" step="0.01" value="'+esc(custom)+'" placeholder="Custom" aria-label="Custom price for '+esc(title)+'"></td>'+
                '<td><strong class="quote-final-price">'+money(displayedFinal)+'</strong></td>';
            tbody.appendChild(tr);
        });
        updatePricing();
    }

    function currentSupportPackage() {
        return (data.supportPackages || []).find(function (item) { return Number(item.id) === Number(supportPackageId); }) || null;
    }

    function captureSupportState() {
        if (!supportPackageId) return {};
        var state={};
        document.querySelectorAll('#support-service-rows tr').forEach(function(row){
            if(!row._packageItem)return;
            var discount=Math.min(100,Math.max(0,Number(row.querySelector('.support-item-discount').value||0)));
            var customText=row.querySelector('.support-item-custom').value.trim();
            var custom=customText===''?null:Math.max(0,Number(customText||0));
            var list=Number(row._packageItem.unit_price||0);
            state[String(row._packageItem.service_id)]={enabled:row.querySelector('.support-item-enabled').checked,discount_percent:discount,custom_price:custom,final_price:custom===null?Math.round(list*(1-discount/100)*100)/100:custom,work_items:clone(row._packageItem.work_items||[])};
        });
        supportStateByPackage[String(supportPackageId)]=state;
        return state;
    }

    function readSupportRows() {
        var rows=[];
        document.querySelectorAll('#support-service-rows tr').forEach(function(row){
            var item=row._packageItem;if(!item||!row.querySelector('.support-item-enabled').checked)return;
            var list=Number(item.unit_price||0),discount=Math.min(100,Math.max(0,Number(row.querySelector('.support-item-discount').value||0)));
            var customText=row.querySelector('.support-item-custom').value.trim(),custom=customText===''?null:Math.max(0,Number(customText||0));
            var finalPrice=custom===null?Math.round(list*(1-discount/100)*100)/100:custom;
            rows.push({service_id:Number(item.service_id),list_price:list,discount_percent:discount,custom_price:custom,final_price:finalPrice,description:item.description||item.service_description||'',description_ar:item.description_ar||item.service_description_ar||'',description_he:item.description_he||item.service_description_he||'',work_items:clone(item.work_items||[])});
        });
        supportActiveItems=rows;return rows;
    }

    function renderSupportServices() {
        var pack=currentSupportPackage(),tbody=document.getElementById('support-service-rows');
        tbody.innerHTML='';supportActiveItems=[];
        if(!pack)return;
        var preserved=supportStateByPackage[String(pack.id)]||{};
        (pack.items||[]).forEach(function(item,index){
            var original=Number(proposal&&proposal.support_package_id)===Number(pack.id)?originalSupportItems[String(item.service_id)]:null;
            var saved=preserved[String(item.service_id)]||null,key=supportWorkSelectionKey(pack.id,item.service_id);
            if(saved)workItemSelections[key]=clone(saved.work_items||[]);else if(!workItemSelections[key])workItemSelections[key]=quoteWorkSelection(item.work_items||[],original&&original.work_items);
            var viewItem=clone(item);viewItem.work_items=workItemSelections[key];
            var enabled=saved?Boolean(saved.enabled):(original?true:Number(item.included)===1);
            var discount=saved?Number(saved.discount_percent||0):(original?Number(original.discount_percent||0):0),list=Number(item.unit_price||0);
            var originalMonthly=original&&Number(original.quantity||0)>0?Number(original.total||0)/Number(original.quantity):null;
            var expected=Math.round(list*(1-discount/100)*100)/100;
            var custom=saved?(saved.custom_price===null?'':Number(saved.custom_price)):(original&&Math.abs(originalMonthly-expected)>0.009?originalMonthly:'');
            var displayed=saved?Number(saved.final_price||0):(original?originalMonthly:list),title=localized(item,'name'),description=localized(item,'description')||localized(item,'service_description');
            var tr=document.createElement('tr');tr._packageItem=viewItem;tr._workSelectionKey=key;
            tr.innerHTML='<td><label class="quote-check"><input class="support-item-enabled" type="checkbox" '+(enabled?'checked':'')+'><span></span></label></td><td><span class="quote-row-number">'+(index+1)+'</span></td><td><div class="quote-service-title"><strong>'+esc(title)+'</strong><button type="button" class="quote-scope-help" data-scope-help title="Edit quote items"><i class="fal fa-pencil"></i></button></div><small><b>'+esc(item.category||'')+'</b> · '+esc(description)+'<span class="quote-work-summary">'+((viewItem.work_items||[]).length?' · '+(viewItem.work_items||[]).filter(function(work){return Number(work.included)===1;}).length+' quote items':'')+'</span></small></td><td class="quote-list-price">'+money(list)+'</td><td><input class="quote-price-input support-item-discount" type="number" min="0" max="100" step="0.01" value="'+esc(discount)+'"></td><td><input class="quote-price-input support-item-custom" type="number" min="0" step="0.01" value="'+esc(custom)+'" placeholder="Custom"></td><td><strong class="support-final-price">'+money(displayed)+'</strong></td>';
            tbody.appendChild(tr);
        });
        updateSupportPricing();
    }

    function updateSupportPricing() {
        document.querySelectorAll('#support-service-rows tr').forEach(function(row){
            var enabled=row.querySelector('.support-item-enabled').checked,list=Number(row._packageItem.unit_price||0),discount=Math.min(100,Math.max(0,Number(row.querySelector('.support-item-discount').value||0))),customText=row.querySelector('.support-item-custom').value.trim();
            var total=customText===''?list*(1-discount/100):Math.max(0,Number(customText||0));row.classList.toggle('excluded',!enabled);row.querySelector('.support-final-price').textContent=enabled?money(total):money(0);
        });
        readSupportRows();renderPlans();updateReview();
    }

    function updatePricing() {
        document.querySelectorAll('#quote-service-rows tr').forEach(function (row) {
            var enabled = row.querySelector('.quote-item-enabled').checked;
            var list = Number(row._packageItem.unit_price || 0);
            var discount = Math.min(100, Math.max(0, Number(row.querySelector('.quote-item-discount').value || 0)));
            var customText = row.querySelector('.quote-item-custom').value.trim();
            var total = customText === '' ? list * (1 - discount / 100) : Math.max(0, Number(customText || 0));
            row.classList.toggle('excluded', !enabled);
            row.querySelector('.quote-final-price').textContent = enabled ? money(total) : money(0);
        });
        readRows(); renderPlans(); updateReview();
    }

    function assessmentState() {
        var answers = {}, score = 0;
        root.querySelectorAll('.assessment-answer').forEach(function (select) { var option=select.options[select.selectedIndex]; answers[select.dataset.question]=select.value; score += Number(option.dataset.score || 0); });
        return {answers:answers, score:score};
    }
    function updateAssessment(allowRecommendation) {
        var result = assessmentState();
        var recommendation = result.score <= Number(recommendationRules.basic_max || 7) ? 'basic' : (result.score <= Number(recommendationRules.medium_max || 14) ? 'medium' : 'pro');
        document.getElementById('scope-score').textContent = result.score + ' / ' + Number(recommendationRules.max_score || 20);
        document.getElementById('recommended-tier').textContent = recommendation.toUpperCase();
        document.getElementById('assessment-json').value = JSON.stringify(result.answers);
        if (allowRecommendation) { tier = recommendation; renderServices(false); }
    }

    function applyTierPreset(nextTier) {
        tier = nextTier;
        var presets = recommendationRules.presets || {basic:0,medium:1,pro:2};
        if (presets[nextTier] == null) { renderServices(false); return; }
        var optionIndex = Number(presets[nextTier]);
        root.querySelectorAll('.assessment-answer').forEach(function (select) {
            select.selectedIndex = Math.min(optionIndex, select.options.length - 1);
        });
        updateAssessment(false);
        renderServices(false);
    }

    function quoteWorkItemEditor(work, index) {
        var title=work.title||work.name||'',description=work.description||work.task_description||'',scope=work.scope_value||work.value_label||'';
        var enabled=work.included==null||Number(work.included)===1;
        function option(value,label,current){return '<option value="'+value+'"'+(String(current||'')===value?' selected':'')+'>'+label+'</option>';}
        return '<article class="quote-work-item-editor" data-work-index="'+index+'"><header><span>'+(index+1)+'</span><strong>'+esc(title||'Quote item')+'</strong><label><input class="quote-work-enabled" type="checkbox" '+(enabled?'checked':'')+'> Included</label></header><div class="quote-work-item-fields">'+
            '<label><span>Name (English)</span><input class="quote-work-title" value="'+esc(title)+'"></label><label><span>Name (Arabic)</span><input dir="rtl" class="quote-work-title-ar" value="'+esc(work.title_ar||work.name_ar||'')+'"></label><label><span>Name (Hebrew)</span><input dir="rtl" class="quote-work-title-he" value="'+esc(work.title_he||work.name_he||'')+'"></label>'+
            '<label class="wide"><span>Task instructions (English)</span><textarea class="quote-work-description" rows="2">'+esc(description)+'</textarea></label><label><span>Arabic instructions</span><textarea dir="rtl" class="quote-work-description-ar" rows="2">'+esc(work.description_ar||work.task_description_ar||'')+'</textarea></label><label><span>Hebrew instructions</span><textarea dir="rtl" class="quote-work-description-he" rows="2">'+esc(work.description_he||work.task_description_he||'')+'</textarea></label>'+
            '<label><span>Final scope / value</span><input class="quote-work-scope" value="'+esc(scope)+'" placeholder="e.g. 40 edited photos"></label><label><span>Arabic value</span><input dir="rtl" class="quote-work-scope-ar" value="'+esc(work.scope_value_ar||work.value_label_ar||'')+'"></label><label><span>Hebrew value</span><input dir="rtl" class="quote-work-scope-he" value="'+esc(work.scope_value_he||work.value_label_he||'')+'"></label>'+
            '<label><span>Work type</span><select class="quote-work-schedule">'+option('one_time','One time',work.schedule_type)+option('recurring','Recurring',work.schedule_type)+'</select></label><label><span>Repeat frequency</span><select class="quote-work-frequency">'+option('daily','Daily',work.frequency)+option('weekly','Weekly',work.frequency||'weekly')+option('monthly','Monthly',work.frequency)+option('yearly','Yearly',work.frequency)+'</select></label></div></article>';
    }

    function openQuoteWorkItems(row) {
        scopeEditingRow=row;
        var item=row._packageItem,workItems=item.work_items||[],workList=document.getElementById('quote-scope-work-items');
        document.getElementById('quote-scope-title').textContent=localized(item,'name');
        document.getElementById('quote-scope-description').textContent=localized(item,'description')||localized(item,'service_description');
        workList.innerHTML=workItems.map(quoteWorkItemEditor).join('')||'<p>No service items are configured for this package.</p>';
        workList.hidden=false;
        document.getElementById('quote-scope-modal').hidden=false;
        document.body.classList.add('quote-modal-open');
    }

    function applyQuoteWorkItems() {
        if (!scopeEditingRow) return;
        var current=scopeEditingRow._packageItem.work_items||[];
        var updated=Array.prototype.map.call(document.querySelectorAll('#quote-scope-work-items .quote-work-item-editor'),function(card,index){
            var original=current[index]||{};
            return Object.assign({},original,{
                service_work_item_id:original.service_work_item_id||original.id,
                included:card.querySelector('.quote-work-enabled').checked?1:0,
                name:card.querySelector('.quote-work-title').value.trim(),title:card.querySelector('.quote-work-title').value.trim(),
                name_ar:card.querySelector('.quote-work-title-ar').value.trim(),title_ar:card.querySelector('.quote-work-title-ar').value.trim(),
                name_he:card.querySelector('.quote-work-title-he').value.trim(),title_he:card.querySelector('.quote-work-title-he').value.trim(),
                task_description:card.querySelector('.quote-work-description').value.trim(),description:card.querySelector('.quote-work-description').value.trim(),
                task_description_ar:card.querySelector('.quote-work-description-ar').value.trim(),description_ar:card.querySelector('.quote-work-description-ar').value.trim(),
                task_description_he:card.querySelector('.quote-work-description-he').value.trim(),description_he:card.querySelector('.quote-work-description-he').value.trim(),
                value_label:card.querySelector('.quote-work-scope').value.trim(),scope_value:card.querySelector('.quote-work-scope').value.trim(),
                value_label_ar:card.querySelector('.quote-work-scope-ar').value.trim(),scope_value_ar:card.querySelector('.quote-work-scope-ar').value.trim(),
                value_label_he:card.querySelector('.quote-work-scope-he').value.trim(),scope_value_he:card.querySelector('.quote-work-scope-he').value.trim(),
                schedule_type:card.querySelector('.quote-work-schedule').value,frequency:card.querySelector('.quote-work-frequency').value
            });
        });
        scopeEditingRow._packageItem.work_items=updated;
        workItemSelections[scopeEditingRow._workSelectionKey||workSelectionKey(scopeEditingRow._packageItem.service_id)]=updated;
        var summary=scopeEditingRow.querySelector('.quote-work-summary');
        if(summary)summary.textContent=updated.length?' · '+updated.filter(function(work){return Number(work.included)===1;}).length+' quote items':'';
        document.getElementById('quote-scope-modal').hidden=true;
        document.body.classList.remove('quote-modal-open');
        scopeEditingRow=null;
        updatePricing();
    }

    function renderWeightList() {
        var host = document.getElementById('quote-weight-list');
        if (!host) return;
        host.innerHTML = '';
        (data.assessment || []).forEach(function (question) {
            var article = document.createElement('article');
            var options = (question.options || []).map(function (option) {
                return '<span>'+esc(localized(option,'label'))+' <b>+'+Number(option.score || 0)+'</b></span>';
            }).join('');
            article.innerHTML = '<strong>'+esc(localized(question,'label'))+'</strong><div>'+options+'</div>';
            host.appendChild(article);
        });
    }

    function renderPlans() {
        var setupAmount=activeItems.reduce(function(sum,item){return sum+Number(item.final_price||0);},0);
        var supportHost=document.getElementById('support-plan-options'); supportHost.innerHTML='';
        (data.supportPlans || []).forEach(function(plan){
            var button=document.createElement('button'); button.type='button'; button.className='quote-plan-card'+(plan.code===supportCode?' active':''); button.dataset.support=plan.code;
            var amount=plan.code==='custom' ? customSupportAmount(setupAmount) : setupAmount*Number(plan.rate_percent||0)/100*Number(plan.multiplier||0);
            var customCycles=Math.round(customSupportDuration()/3*100)/100;
            var pricing=plan.code==='custom' ? customSupportRate()+'% × '+customCycles+' = '+money(amount) : (Number(plan.rate_percent)>0 ? Number(plan.rate_percent)+'% × '+Number(plan.multiplier)+' = '+money(amount) : dictionary('no_support'));
            button.innerHTML='<strong>'+esc(localized(plan,'name'))+'</strong><span>'+esc(pricing)+'</span><small>'+esc(localized(plan,'description'))+'</small>'; supportHost.appendChild(button);
        });
        document.getElementById('custom-support-panel').hidden=supportCode!=='custom';
        document.getElementById('custom-support-total').textContent=money(customSupportAmount(setupAmount));
        var memberHost=document.getElementById('support-contract-options'); memberHost.innerHTML='';
        var noContract=document.createElement('button');noContract.type='button';noContract.className='quote-plan-card'+(!supportPackageId?' active':'');noContract.dataset.supportPackage='0';noContract.innerHTML='<strong>'+esc(dictionary('no_support_contract'))+'</strong><span>'+money(0)+'/mo</span><small>'+esc(dictionary('no_support_contract'))+'</small>';memberHost.appendChild(noContract);
        (data.supportPackages || []).forEach(function(plan){
            var monthly=(plan.items||[]).reduce(function(sum,item){return sum+(Number(item.included)===1?Number(item.unit_price||0):0);},0),button=document.createElement('button');button.type='button';button.className='quote-plan-card'+(Number(plan.id)===Number(supportPackageId)?' active':'');button.dataset.supportPackage=String(plan.id);button.innerHTML='<strong>'+esc(localized(plan,'name'))+'</strong><span>'+money(monthly)+'/mo</span><small>'+esc(localized(plan,'description'))+'</small>'+(Number(plan.featured)?'<em>'+esc(dictionary('most_popular'))+'</em>':'');memberHost.appendChild(button);
        });
        var hasMembership=Number(supportPackageId)>0;
        document.getElementById('support-contract-details').hidden=!hasMembership;
        var customDurationActive=hasMembership&&membershipTermMode==='custom';
        document.getElementById('custom-duration-panel').hidden=!customDurationActive;
        var customDurationInput=document.getElementById('custom-membership-duration');
        customDurationInput.disabled=!customDurationActive;
        if(customDurationActive&&Number(customDurationInput.value)<1)customDurationInput.value=String(Math.max(1,membershipDuration||3));
        root.querySelectorAll('[data-membership-term]').forEach(function(button){button.classList.toggle('active',button.dataset.membershipTerm===membershipTermMode);});
        var amount=totals();
        document.getElementById('plan-setup-total').textContent=money(amount.setup);
        document.getElementById('plan-support-total').textContent=money(amount.support);
        document.getElementById('plan-membership-total').textContent=money(amount.membership)+(hasMembership?' · '+membershipDurationValue()+' '+dictionary('months'):'');
        document.getElementById('support-monthly-total').textContent=money(amount.membershipMonthly);
        document.getElementById('support-contract-total').textContent=money(amount.membership);
    }

    function selectedSupport() { return (data.supportPlans || []).find(function(plan){return plan.code===supportCode;}) || {rate_percent:0,multiplier:0}; }
    function customSupportRate() { return Math.min(100,Math.max(0,Number(document.getElementById('custom-support-rate').value||0))); }
    function customSupportDuration() { return Math.min(60,Math.max(1,Number(document.getElementById('custom-support-duration').value||1))); }
    function customSupportAmount(setup) { return Number(setup||0)*customSupportRate()/100*(customSupportDuration()/3); }
    function membershipDurationValue() {
        return membershipTermMode==='custom' ? Math.min(60,Math.max(1,Number(document.getElementById('custom-membership-duration').value||1))) : Number(membershipTermMode||3);
    }
    function totals() {
        var setup=activeItems.reduce(function(sum,item){return sum+Number(item.final_price||0);},0);
        var support=selectedSupport(); var supportAmount=supportCode==='custom' ? customSupportAmount(setup) : setup*Number(support.rate_percent||0)/100*Number(support.multiplier||0);
        var monthlyPrice=supportActiveItems.reduce(function(sum,item){return sum+Number(item.final_price||0);},0);
        var membershipAmount=!supportPackageId ? 0 : monthlyPrice*membershipDurationValue(); var subtotal=setup+supportAmount+membershipAmount;
        var tax=document.getElementById('tax-enabled').checked?subtotal*0.13:0;
        return {setup:setup,support:supportAmount,membership:membershipAmount,membershipMonthly:monthlyPrice,membershipDuration:!supportPackageId?0:membershipDurationValue(),subtotal:subtotal,tax:tax,total:subtotal+tax};
    }
    function updateReview() {
        if (!document.getElementById('review-items')) return;
        readRows(); readSupportRows(); var amount=totals();
        document.getElementById('setup-subtotal').textContent=money(amount.setup);
        document.getElementById('review-business').textContent=form.elements.business_name.value || 'Business Name';
        document.getElementById('review-contact').textContent=form.elements.contact_name.value || 'Client Name';
        document.getElementById('review-tier').textContent=tier.toUpperCase();
        function adjustment(item) {
            if (item.custom_price !== null && item.custom_price !== '' && item.custom_price !== undefined) return dictionary('custom_price');
            return Number(item.discount_percent||0)>0 ? '-'+Number(item.discount_percent||0)+'%' : '—';
        }
        function workItems(item) {
            var rows=(item.work_items||[]).filter(function(work){return work.included==null||Number(work.included)===1;});
            if(!rows.length)return '';
            return '<ul class="quote-review-deliverables">'+rows.map(function(work){var scope=localized(work,'scope_value')||localized(work,'value_label');return '<li><i class="fal fa-check-circle"></i><span><b>'+esc(localized(work,'title')||localized(work,'name'))+'</b>'+(scope?'<small>'+esc(scope)+'</small>':'')+'</span></li>';}).join('')+'</ul>';
        }
        var host=document.getElementById('review-items');
        var pack=currentPackage();
        var setupLines=activeItems.map(function(item,index){var source=(pack.items||[]).find(function(row){return Number(row.service_id)===Number(item.service_id);})||{};return '<div class="quote-review-cost-row"><span class="quote-review-number">'+(index+1)+'</span><div class="quote-review-service"><strong>'+esc(localized(source,'name'))+'</strong><small>'+esc(localized(source,'description')||localized(source,'service_description'))+'</small>'+workItems(item)+'</div><span class="quote-review-price"><small>'+esc(dictionary('list_rate'))+'</small><b>'+money(item.list_price)+'</b></span><span class="quote-review-price"><small>'+esc(dictionary('adjustment'))+'</small><b>'+esc(adjustment(item))+'</b></span><span class="quote-review-price quote-review-price--total"><small>'+esc(dictionary('one_time_total'))+'</small><b>'+money(item.final_price)+'</b></span></div>';}).join('');
        var setupSection='<section class="quote-review-section quote-review-section--setup"><header><span>01</span><div><h5>'+esc(dictionary('setup_services_title'))+'</h5><p>'+esc(dictionary('setup_services_help'))+'</p></div><strong>'+money(amount.setup)+'</strong></header><div class="quote-review-column-head"><span></span><span>'+esc(dictionary('scope_and_deliverables'))+'</span><span>'+esc(dictionary('list_rate'))+'</span><span>'+esc(dictionary('adjustment'))+'</span><span>'+esc(dictionary('one_time_total'))+'</span></div>'+setupLines+'<footer><span>'+esc(dictionary('setup_subtotal'))+'</span><b>'+money(amount.setup)+'</b></footer></section>';
        var selectedSupportPlan=selectedSupport();
        var supportSelected=supportCode!=='none'&&Number(amount.support)>0;
        var supportName=supportSelected?localized(selectedSupportPlan,'name'):dictionary('not_selected');
        if(supportCode==='custom'&&document.getElementById('custom-support-name').value.trim())supportName=document.getElementById('custom-support-name').value.trim();
        var supportDescription=supportSelected?localized(selectedSupportPlan,'description'):dictionary('no_support');
        var supportRate=supportCode==='custom'?customSupportRate():Number(selectedSupportPlan.rate_percent||0);
        var supportCycles=supportCode==='custom'?(customSupportDuration()/3):Number(selectedSupportPlan.multiplier||0);
        var supportDuration=supportCode==='custom'?customSupportDuration():Number(selectedSupportPlan.duration_months||0);
        var supportFormula=supportSelected?money(amount.setup)+' × '+supportRate+'% × '+Math.round(supportCycles*100)/100:'—';
        var supportSection='<section class="quote-review-section quote-review-section--technical"><header><span>02</span><div><h5>'+esc(dictionary('technical_support'))+'</h5><p>'+esc(dictionary('technical_support_help'))+'</p></div><strong>'+money(amount.support)+'</strong></header><div class="quote-review-support-card"><div><small>'+esc(dictionary('technical_support'))+'</small><strong>'+esc(supportName)+'</strong><p>'+esc(supportDescription)+'</p></div><div><small>'+esc(dictionary('support_duration'))+'</small><strong>'+(supportSelected?supportDuration+' '+esc(dictionary('months')):'—')+'</strong></div><div><small>'+esc(dictionary('pricing_basis'))+'</small><strong>'+esc(supportFormula)+'</strong></div><div class="quote-review-support-total"><small>'+esc(dictionary('calculated_support'))+'</small><strong>'+money(amount.support)+'</strong></div></div></section>';
        var supportPack=currentSupportPackage();
        var contractLines='';
        if(supportPack&&supportActiveItems.length){contractLines=supportActiveItems.map(function(item,index){var source=(supportPack.items||[]).find(function(row){return Number(row.service_id)===Number(item.service_id);})||{};return '<div class="quote-review-cost-row quote-review-cost-row--contract"><span class="quote-review-number">'+(index+1)+'</span><div class="quote-review-service"><strong>'+esc(localized(source,'name'))+'</strong><small>'+esc(localized(source,'description')||localized(source,'service_description'))+'</small>'+workItems(item)+'</div><span class="quote-review-price"><small>'+esc(dictionary('list_rate'))+'</small><b>'+money(item.list_price)+'/mo</b></span><span class="quote-review-price"><small>'+esc(dictionary('adjustment'))+'</small><b>'+esc(adjustment(item))+'</b></span><span class="quote-review-price"><small>'+esc(dictionary('monthly_rate'))+'</small><b>'+money(item.final_price)+'</b></span><span class="quote-review-price quote-review-price--total"><small>'+esc(dictionary('contract_value'))+'</small><b>'+money(Number(item.final_price)*membershipDurationValue())+'</b></span></div>';}).join('');}
        else{contractLines='<div class="quote-review-empty">'+esc(dictionary('no_support_contract'))+'</div>';}
        var supportPackageName=supportPack?localized(supportPack,'name'):dictionary('not_selected');
        var contractSection='<section class="quote-review-section quote-review-section--contract"><header><span>03</span><div><h5>'+esc(dictionary('support_contract_services'))+'</h5><p>'+esc(dictionary('support_contract_services_help'))+'</p></div><strong>'+money(amount.membership)+'</strong></header>'+(supportPack?'<div class="quote-review-contract-meta"><div><small>'+esc(dictionary('support_package'))+'</small><strong>'+esc(supportPackageName)+'</strong></div><div><small>'+esc(dictionary('monthly_subtotal'))+'</small><strong>'+money(amount.membershipMonthly)+'</strong></div><div><small>'+esc(dictionary('contract_term'))+'</small><strong>'+amount.membershipDuration+' '+esc(dictionary('months'))+'</strong></div><div><small>'+esc(dictionary('contract_value'))+'</small><strong>'+money(amount.membership)+'</strong></div></div>':'')+contractLines+'<footer><span>'+esc(dictionary('monthly_subtotal'))+' '+money(amount.membershipMonthly)+' × '+amount.membershipDuration+' '+esc(dictionary('months'))+'</span><b>'+money(amount.membership)+'</b></footer></section>';
        host.innerHTML=setupSection+supportSection+contractSection;
        document.getElementById('review-overview-setup').textContent=money(amount.setup);
        document.getElementById('review-overview-support').textContent=money(amount.support);
        document.getElementById('review-overview-monthly').textContent=money(amount.membershipMonthly);
        document.getElementById('review-overview-term').textContent=amount.membershipDuration?amount.membershipDuration+' '+dictionary('months'):'—';
        document.getElementById('review-membership-detail').textContent=amount.membershipDuration?money(amount.membershipMonthly)+'/mo × '+amount.membershipDuration+' '+dictionary('months'):'';
        ['setup','support','membership','subtotal','tax','total'].forEach(function(key){document.getElementById('review-'+key).textContent=money(amount[key]);});
        document.getElementById('items-json').value=JSON.stringify(activeItems);
        document.getElementById('support-items-json').value=JSON.stringify(supportActiveItems);document.getElementById('support-plan-input').value=supportCode;document.getElementById('support-package-input').value=String(supportPackageId||0);document.getElementById('membership-term-input').value=amount.membershipDuration;
    }

    function showStep(next) {
        step=Math.max(1,Math.min(5,next));
        root.querySelectorAll('[data-step]').forEach(function(panel){panel.classList.toggle('active',Number(panel.dataset.step)===step);});
        root.querySelectorAll('[data-step-tab]').forEach(function(tab){var n=Number(tab.dataset.stepTab);tab.classList.toggle('active',n===step);tab.classList.toggle('complete',n<step);});
        document.getElementById('quote-back').style.visibility=step===1?'hidden':'visible';
        document.getElementById('quote-next').classList.toggle('d-none',step===5); document.getElementById('quote-save').classList.toggle('d-none',step!==5);
        if(step===5)updateReview(); root.scrollIntoView({behavior:'smooth',block:'start'});
    }
    function validCurrentStep(){if(step!==1)return true;var fields=root.querySelector('[data-step="1"]').querySelectorAll('[required]');for(var i=0;i<fields.length;i++){if(!fields[i].checkValidity()){fields[i].reportValidity();return false;}}return true;}

    var localeSelector=document.getElementById('quote-locale');
    if(localeSelector)localeSelector.addEventListener('change',function(){locale=this.value;applyLanguage(false);});
    document.getElementById('tier-override').addEventListener('change',function(){applyTierPreset(this.value);});
    root.addEventListener('click',function(event){
        var tierButton=event.target.closest('[data-tier]');if(tierButton){applyTierPreset(tierButton.dataset.tier);return;}
        var supportButton=event.target.closest('[data-support]');if(supportButton){supportCode=supportButton.dataset.support;renderPlans();updateReview();return;}
        var memberButton=event.target.closest('[data-support-package]');if(memberButton){captureSupportState();supportPackageId=Number(memberButton.dataset.supportPackage||0);renderSupportServices();renderPlans();updateReview();return;}
        var termButton=event.target.closest('[data-membership-term]');if(termButton){membershipTermMode=termButton.dataset.membershipTerm;if(membershipTermMode!=='custom')membershipDuration=Number(membershipTermMode);renderPlans();updateReview();return;}
        var scoreButton=event.target.closest('[data-score-help]');if(scoreButton){renderWeightList();document.getElementById('quote-score-modal').hidden=false;document.body.classList.add('quote-modal-open');return;}
        var scopeButton=event.target.closest('[data-scope-help]');if(scopeButton){var row=scopeButton.closest('tr');if(row&&row._packageItem)openQuoteWorkItems(row);return;}
        var stepButton=event.target.closest('[data-step-tab]');if(stepButton){var destination=Number(stepButton.dataset.stepTab);if(destination<=step||validCurrentStep())showStep(destination);}
    });
    document.querySelectorAll('[data-scope-close]').forEach(function(button){button.addEventListener('click',function(){document.getElementById('quote-scope-modal').hidden=true;document.body.classList.remove('quote-modal-open');});});
    document.getElementById('quote-scope-apply').addEventListener('click',applyQuoteWorkItems);
    document.querySelectorAll('[data-score-close]').forEach(function(button){button.addEventListener('click',function(){document.getElementById('quote-score-modal').hidden=true;document.body.classList.remove('quote-modal-open');});});
    document.addEventListener('keydown',function(event){if(event.key==='Escape'){document.getElementById('quote-scope-modal').hidden=true;document.getElementById('quote-score-modal').hidden=true;document.body.classList.remove('quote-modal-open');}});
    document.getElementById('quote-service-rows').addEventListener('input',updatePricing);
    document.getElementById('quote-service-rows').addEventListener('change',updatePricing);
    document.getElementById('support-service-rows').addEventListener('input',updateSupportPricing);
    document.getElementById('support-service-rows').addEventListener('change',updateSupportPricing);
    root.querySelectorAll('.assessment-answer').forEach(function(select){select.addEventListener('change',function(){updateAssessment(true);});});
    ['custom-support-name','custom-support-rate','custom-support-duration','custom-membership-duration'].forEach(function(id){document.getElementById(id).addEventListener('input',function(){if(id==='custom-membership-duration')membershipDuration=membershipDurationValue();renderPlans();updateReview();});});
    document.getElementById('tax-enabled').addEventListener('change',updateReview);
    document.getElementById('quote-next').addEventListener('click',function(){if(validCurrentStep())showStep(step+1);});
    document.getElementById('quote-back').addEventListener('click',function(){showStep(step-1);});
    form.addEventListener('input',function(event){if(event.target.closest('[data-step="1"]'))updateReview();});
    form.addEventListener('submit',function(event){updateAssessment(false);updateReview();if(!activeItems.length){event.preventDefault();window.alert('Select at least one setup service.');}});
    relationshipSelect.addEventListener('change',function(){
        clearClientDetails();
        var relationship=(data.relationships||[]).find(function(row){return row.key===this.value;},this);
        if(relationship){
            form.elements.contact_name.value=relationship.contact_name||'';
            form.elements.business_name.value=relationship.business_name||'';
            form.elements.contact_email.value=relationship.email||'';
            form.elements.contact_phone.value=relationship.phone||'';
            form.elements.website.value=relationship.website||'';
            form.elements.internal_notes.value=relationship.internal_notes||'';
            form.elements.years_operating.value=relationship.years_in_business == null ? '' : relationship.years_in_business;
            form.elements.business_stage.value=relationship.business_stage||'';
        }
        syncRelationshipPicker();
        updateReview();
    });
    relationshipSearch.addEventListener('focus',function(){this.select();openRelationshipPicker(true);});
    relationshipSearch.addEventListener('input',function(){openRelationshipPicker(false);});
    relationshipSearch.addEventListener('keydown',function(event){
        var buttons=visibleRelationshipButtons();
        if(event.key==='ArrowDown'){event.preventDefault();if(relationshipList.hidden)openRelationshipPicker(false);focusRelationshipOption(relationshipActiveIndex+1);}
        else if(event.key==='ArrowUp'){event.preventDefault();focusRelationshipOption(relationshipActiveIndex<0?buttons.length-1:relationshipActiveIndex-1);}
        else if(event.key==='Enter'&&!relationshipList.hidden){event.preventDefault();buttons=visibleRelationshipButtons();if(buttons.length)buttons[Math.max(0,relationshipActiveIndex)].click();}
        else if(event.key==='Escape'){closeRelationshipPicker();syncRelationshipPicker();}
    });
    relationshipToggle.addEventListener('click',function(){if(relationshipList.hidden){relationshipSearch.focus();openRelationshipPicker(true);}else{closeRelationshipPicker();syncRelationshipPicker();}});
    document.addEventListener('click',function(event){if(!event.target.closest('#quote-relationship-combobox')){closeRelationshipPicker();syncRelationshipPicker();}});

    root.querySelectorAll('[data-step-tab]').forEach(function(button){button.setAttribute('aria-selected',button.dataset.stepTab==='1'?'true':'false');});
    syncRelationshipPicker(); renderWeightList(); document.getElementById('tier-override').value=tier; updateAssessment(false); renderSupportServices(); applyLanguage(!!proposal); showStep(1);
})();
