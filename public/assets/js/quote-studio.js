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
    var membershipCode = (proposal && proposal.membership_plan) || 'none';
    var recommendationRules = data.recommendation || {basic_max:7, medium_max:14, max_score:20, presets:{basic:0,medium:1,pro:2}};
    var membershipDuration = Number((proposal && proposal.membership_duration_months) || 3);
    var membershipTermMode = [3,12,18].indexOf(membershipDuration) >= 0 ? String(membershipDuration) : 'custom';
    var activeItems = [];
    var originalItems = {};
    (data.initialItems || []).forEach(function (item) { originalItems[String(item.service_id)] = item; });

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

    function applyLanguage() {
        root.setAttribute('dir', locale === 'en' ? 'ltr' : 'rtl');
        root.dataset.locale = locale;
        document.getElementById('quote-locale-input').value = locale;
        root.querySelectorAll('[data-i18n]').forEach(function (node) {
            var key = node.dataset.i18n; if (dictionaries[locale] && dictionaries[locale][key]) node.textContent = dictionaries[locale][key];
        });
        root.querySelectorAll('.assessment-label').forEach(function (node) { node.textContent = node.dataset[locale] || node.dataset.en; });
        root.querySelectorAll('.assessment-answer option').forEach(function (option) { option.textContent = option.dataset[locale] || option.dataset.en; });
        renderWeightList(); renderServices(false); renderPlans(); updateReview();
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
            rows.push({service_id:Number(packageItem.service_id), list_price:list, discount_percent:discount, custom_price:custom, final_price:finalPrice, description:packageItem.description || packageItem.service_description || '', description_ar:packageItem.description_ar || packageItem.service_description_ar || '', description_he:packageItem.description_he || packageItem.service_description_he || ''});
        });
        activeItems = rows;
        return rows;
    }

    function renderServices(useOriginal) {
        var pack = currentPackage(); if (!pack) return;
        document.getElementById('selected-tier').value = tier;
        document.getElementById('tier-override').value = tier;
        root.querySelectorAll('[data-tier]').forEach(function (button) { button.classList.toggle('active', button.dataset.tier === tier); });
        var tbody = document.getElementById('quote-service-rows');
        tbody.innerHTML = '';
        (pack.items || []).forEach(function (item, index) {
            var original = useOriginal ? originalItems[String(item.service_id)] : null;
            var enabled = original ? true : Number(item.included) === 1;
            var discount = original ? Number(original.discount_percent || 0) : 0;
            var originalFinal = original ? Number(original.total || 0) : null;
            var list = Number(item.unit_price || 0);
            var expectedDiscounted = Math.round(list * (1 - discount / 100) * 100) / 100;
            var custom = original && Math.abs(originalFinal - expectedDiscounted) > 0.009 ? originalFinal : '';
            var title = localized(item, 'name');
            var description = localized(item, 'description') || localized(item, 'service_description');
            var tr = document.createElement('tr'); tr._packageItem = item;
            tr.innerHTML = '<td><label class="quote-check"><input class="quote-item-enabled" type="checkbox" '+(enabled?'checked':'')+'><span></span></label></td>'+
                '<td><span class="quote-row-number">'+(index+1)+'</span></td>'+
                '<td><div class="quote-service-title"><strong>'+esc(title)+'</strong><button type="button" class="quote-scope-help" data-scope-help title="'+esc(dictionary('scope_details'))+'" aria-label="'+esc(dictionary('scope_details'))+'">?</button></div><small><b>'+esc(item.category || '')+'</b> · '+esc(description)+'</small></td>'+
                '<td class="quote-list-price">'+money(list)+'</td>'+
                '<td><input class="quote-price-input quote-item-discount" type="number" min="0" max="100" step="0.01" value="'+esc(discount)+'"></td>'+
                '<td><input class="quote-price-input quote-item-custom" type="number" min="0" step="0.01" value="'+esc(custom)+'" placeholder="Custom"></td>'+
                '<td><strong class="quote-final-price">'+money(original ? originalFinal : list)+'</strong></td>';
            tbody.appendChild(tr);
        });
        updatePricing();
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
        var optionIndex = Number(presets[nextTier] == null ? 0 : presets[nextTier]);
        root.querySelectorAll('.assessment-answer').forEach(function (select) {
            select.selectedIndex = Math.min(optionIndex, select.options.length - 1);
        });
        updateAssessment(false);
        renderServices(false);
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
            var amount=plan.code==='custom' ? customSupportAmount() : setupAmount*Number(plan.rate_percent||0)/100*Number(plan.multiplier||0);
            var pricing=plan.code==='custom' ? money(amount) : (Number(plan.rate_percent)>0 ? Number(plan.rate_percent)+'% × '+Number(plan.multiplier)+' = '+money(amount) : dictionary('no_support'));
            button.innerHTML='<strong>'+esc(localized(plan,'name'))+'</strong><span>'+esc(pricing)+'</span><small>'+esc(localized(plan,'description'))+'</small>'; supportHost.appendChild(button);
        });
        document.getElementById('custom-support-panel').hidden=supportCode!=='custom';
        var memberHost=document.getElementById('membership-plan-options'); memberHost.innerHTML='';
        (data.memberships || []).forEach(function(plan){
            var button=document.createElement('button'); button.type='button'; button.className='quote-plan-card'+(plan.code===membershipCode?' active':''); button.dataset.membership=plan.code;
            var monthly=plan.code==='custom' ? customMembershipMonthlyPrice() : Number(plan.monthly_price||0);
            var pricing=monthly>0 ? money(monthly)+'/mo' : (plan.code==='custom' ? dictionary('custom_plan') : dictionary('no_membership'));
            button.innerHTML='<strong>'+esc(localized(plan,'name'))+'</strong><span>'+esc(pricing)+'</span><small>'+esc(localized(plan,'description'))+'</small>'+(Number(plan.featured)?'<em>'+esc(dictionary('most_popular'))+'</em>':''); memberHost.appendChild(button);
        });
        var hasMembership=membershipCode!=='none';
        document.getElementById('membership-term-panel').hidden=!hasMembership;
        document.getElementById('custom-membership-panel').hidden=membershipCode!=='custom';
        document.getElementById('custom-duration-panel').hidden=membershipTermMode!=='custom';
        root.querySelectorAll('[data-membership-term]').forEach(function(button){button.classList.toggle('active',button.dataset.membershipTerm===membershipTermMode);});
        var amount=totals();
        document.getElementById('plan-setup-total').textContent=money(amount.setup);
        document.getElementById('plan-support-total').textContent=money(amount.support);
        document.getElementById('plan-membership-total').textContent=money(amount.membership)+(hasMembership?' · '+membershipDurationValue()+' '+dictionary('months'):'');
    }

    function selectedSupport() { return (data.supportPlans || []).find(function(plan){return plan.code===supportCode;}) || {rate_percent:0,multiplier:0}; }
    function selectedMembership() { return (data.memberships || []).find(function(plan){return plan.code===membershipCode;}) || {monthly_price:0}; }
    function customSupportAmount() { return Math.max(0,Number(document.getElementById('custom-support-amount').value||0)); }
    function customMembershipMonthlyPrice() { return Math.max(0,Number(document.getElementById('custom-membership-price').value||0)); }
    function membershipDurationValue() {
        return membershipTermMode==='custom' ? Math.min(60,Math.max(1,Number(document.getElementById('custom-membership-duration').value||1))) : Number(membershipTermMode||3);
    }
    function totals() {
        var setup=activeItems.reduce(function(sum,item){return sum+Number(item.final_price||0);},0);
        var support=selectedSupport(); var supportAmount=supportCode==='custom' ? customSupportAmount() : setup*Number(support.rate_percent||0)/100*Number(support.multiplier||0);
        var membership=selectedMembership(); var monthlyPrice=membershipCode==='custom' ? customMembershipMonthlyPrice() : Number(membership.monthly_price||0);
        var membershipAmount=membershipCode==='none' ? 0 : monthlyPrice*membershipDurationValue(); var subtotal=setup+supportAmount+membershipAmount;
        var tax=document.getElementById('tax-enabled').checked?subtotal*0.13:0;
        return {setup:setup,support:supportAmount,membership:membershipAmount,membershipMonthly:monthlyPrice,membershipDuration:membershipCode==='none'?0:membershipDurationValue(),subtotal:subtotal,tax:tax,total:subtotal+tax};
    }
    function updateReview() {
        if (!document.getElementById('review-items')) return;
        readRows(); var amount=totals();
        document.getElementById('setup-subtotal').textContent=money(amount.setup);
        document.getElementById('review-business').textContent=form.elements.business_name.value || 'Business Name';
        document.getElementById('review-contact').textContent=form.elements.contact_name.value || 'Client Name';
        document.getElementById('review-tier').textContent=tier.toUpperCase();
        var host=document.getElementById('review-items'); host.innerHTML='<h5>'+esc(dictionary('service'))+'</h5>';
        var pack=currentPackage();
        activeItems.forEach(function(item,index){var source=(pack.items||[]).find(function(row){return Number(row.service_id)===Number(item.service_id);})||{}; var line=document.createElement('div');line.className='quote-review-item';line.innerHTML='<span>'+(index+1)+'</span><div><strong>'+esc(localized(source,'name'))+'</strong><small>'+esc(localized(source,'description')||localized(source,'service_description'))+'</small></div><b>'+money(item.final_price)+'</b>';host.appendChild(line);});
        ['setup','support','membership','subtotal','tax','total'].forEach(function(key){document.getElementById('review-'+key).textContent=money(amount[key]);});
        document.getElementById('items-json').value=JSON.stringify(activeItems);
        document.getElementById('support-plan-input').value=supportCode; document.getElementById('membership-plan-input').value=membershipCode; document.getElementById('membership-term-input').value=amount.membershipDuration;
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
    if(localeSelector)localeSelector.addEventListener('change',function(){locale=this.value;applyLanguage();});
    document.getElementById('tier-override').addEventListener('change',function(){applyTierPreset(this.value);});
    root.addEventListener('click',function(event){
        var tierButton=event.target.closest('[data-tier]');if(tierButton){applyTierPreset(tierButton.dataset.tier);return;}
        var supportButton=event.target.closest('[data-support]');if(supportButton){supportCode=supportButton.dataset.support;renderPlans();updateReview();return;}
        var memberButton=event.target.closest('[data-membership]');if(memberButton){membershipCode=memberButton.dataset.membership;renderPlans();updateReview();return;}
        var termButton=event.target.closest('[data-membership-term]');if(termButton){membershipTermMode=termButton.dataset.membershipTerm;if(membershipTermMode!=='custom')membershipDuration=Number(membershipTermMode);renderPlans();updateReview();return;}
        var scoreButton=event.target.closest('[data-score-help]');if(scoreButton){renderWeightList();document.getElementById('quote-score-modal').hidden=false;document.body.classList.add('quote-modal-open');return;}
        var scopeButton=event.target.closest('[data-scope-help]');if(scopeButton){var row=scopeButton.closest('tr');var item=row&&row._packageItem;if(item){document.getElementById('quote-scope-title').textContent=localized(item,'name');document.getElementById('quote-scope-description').textContent=localized(item,'description')||localized(item,'service_description');var modal=document.getElementById('quote-scope-modal');modal.hidden=false;document.body.classList.add('quote-modal-open');}return;}
        var stepButton=event.target.closest('[data-step-tab]');if(stepButton){var destination=Number(stepButton.dataset.stepTab);if(destination<=step||validCurrentStep())showStep(destination);}
    });
    document.querySelectorAll('[data-scope-close]').forEach(function(button){button.addEventListener('click',function(){document.getElementById('quote-scope-modal').hidden=true;document.body.classList.remove('quote-modal-open');});});
    document.querySelectorAll('[data-score-close]').forEach(function(button){button.addEventListener('click',function(){document.getElementById('quote-score-modal').hidden=true;document.body.classList.remove('quote-modal-open');});});
    document.addEventListener('keydown',function(event){if(event.key==='Escape'){document.getElementById('quote-scope-modal').hidden=true;document.getElementById('quote-score-modal').hidden=true;document.body.classList.remove('quote-modal-open');}});
    document.getElementById('quote-service-rows').addEventListener('input',updatePricing);
    document.getElementById('quote-service-rows').addEventListener('change',updatePricing);
    root.querySelectorAll('.assessment-answer').forEach(function(select){select.addEventListener('change',function(){updateAssessment(true);});});
    ['custom-support-amount','custom-membership-name','custom-membership-price','custom-membership-duration'].forEach(function(id){document.getElementById(id).addEventListener('input',function(){if(id==='custom-membership-duration')membershipDuration=membershipDurationValue();renderPlans();updateReview();});});
    document.getElementById('tax-enabled').addEventListener('change',updateReview);
    document.getElementById('quote-next').addEventListener('click',function(){if(validCurrentStep())showStep(step+1);});
    document.getElementById('quote-back').addEventListener('click',function(){showStep(step-1);});
    form.addEventListener('input',function(event){if(event.target.closest('[data-step="1"]'))updateReview();});
    form.addEventListener('submit',function(event){updateAssessment(false);updateReview();if(!activeItems.length){event.preventDefault();window.alert('Select at least one setup service.');}});
    document.getElementById('existing-relationship').addEventListener('change',function(){var relationship=(data.relationships||[]).find(function(row){return row.key===this.value;},this);if(!relationship)return;form.elements.contact_name.value=relationship.contact_name||'';form.elements.business_name.value=relationship.business_name||'';form.elements.contact_email.value=relationship.email||'';form.elements.contact_phone.value=relationship.phone||'';form.elements.website.value=relationship.website||'';form.elements.years_operating.value=relationship.years_in_business||0;if(relationship.business_stage)form.elements.business_stage.value=relationship.business_stage;updateReview();});

    root.querySelectorAll('[data-step-tab]').forEach(function(button){button.setAttribute('aria-selected',button.dataset.stepTab==='1'?'true':'false');});
    renderWeightList(); document.getElementById('tier-override').value=tier; renderServices(!!proposal); renderPlans(); updateAssessment(false); applyLanguage(); showStep(1);
})();
