<?php
$locale=in_array($locale??'en',['en','ar','he'],true)?$locale:'en';
$rtl=$locale!=='en';
$loc=static function(array $row,string $field)use($locale):string{
    $localized=$locale==='en'?$field:$field.'_'.$locale;
    return trim((string)($row[$localized]??$row[$field]??''));
};
$labels=[
    'en'=>[
        'proposal'=>'COMMERCIAL PROPOSAL','prepared_for'=>'PREPARED FOR','prepared_by'=>'PREPARED BY','valid_until'=>'VALID UNTIL','proposal_no'=>'PROPOSAL NO.','investment'=>'YOUR INVESTMENT','one_time'=>'One-time setup','technical'=>'Technical support','contract'=>'Support contract','grand_total'=>'Grand total','overview'=>'A connected plan for launch, delivery, and ongoing growth.','scope'=>'Scope of work','scope_help'=>'Every service, deliverable, and commercial adjustment included in this proposal.','service'=>'SERVICE AND DELIVERABLES','list'=>'LIST PRICE','adjustment'=>'ADJUSTMENT','final'=>'FINAL PRICE','section_total'=>'SECTION TOTAL','support'=>'Coverage and continuity','support_help'=>'Technical protection plus ongoing service delivery, calculated from the selected commercial terms.','plan'=>'PLAN','term'=>'TERM','basis'=>'PRICING BASIS','amount'=>'AMOUNT','monthly'=>'MONTHLY','contract_value'=>'CONTRACT VALUE','commercial'=>'Commercial summary','subtotal'=>'Subtotal before tax','tax'=>'HST','next_steps'=>'Next steps','next_steps_copy'=>'Approve the proposal, confirm the preferred start date, and our team will schedule the kickoff and delivery plan.','terms'=>'Commercial notes','notes'=>'Prices are in Canadian dollars. Third-party costs are separate unless expressly included. This proposal remains valid until the date shown above.','acceptance'=>'Proposal acceptance','acceptance_copy'=>'By approving this proposal, the client confirms the selected scope, support options, contract term, and commercial totals shown in this document.','client_signature'=>'CLIENT NAME / SIGNATURE','date'=>'DATE','months'=>'months','not_selected'=>'Not selected','monthly_total'=>'Monthly service total','full_term'=>'Full contract term','one_time_label'=>'ONE TIME','recurring_label'=>'RECURRING','revision'=>'REVISION'],
    'ar'=>[
        'proposal'=>'عرض تجاري','prepared_for'=>'مقدم إلى','prepared_by'=>'مقدم من','valid_until'=>'صالح حتى','proposal_no'=>'رقم العرض','investment'=>'قيمة الاستثمار','one_time'=>'إعداد لمرة واحدة','technical'=>'الدعم الفني','contract'=>'عقد الدعم','grand_total'=>'الإجمالي الكلي','overview'=>'خطة متكاملة للإطلاق والتنفيذ والنمو المستمر.','scope'=>'نطاق العمل','scope_help'=>'جميع الخدمات والتسليمات والتعديلات التجارية المشمولة في هذا العرض.','service'=>'الخدمة والتسليمات','list'=>'سعر القائمة','adjustment'=>'التعديل','final'=>'السعر النهائي','section_total'=>'مجموع القسم','support'=>'التغطية والاستمرارية','support_help'=>'حماية فنية وخدمات مستمرة محسوبة وفق الشروط التجارية المختارة.','plan'=>'الخطة','term'=>'المدة','basis'=>'أساس التسعير','amount'=>'المبلغ','monthly'=>'شهرياً','contract_value'=>'قيمة العقد','commercial'=>'الملخص التجاري','subtotal'=>'المجموع قبل الضريبة','tax'=>'HST','next_steps'=>'الخطوات التالية','next_steps_copy'=>'اعتمد العرض وحدد تاريخ البدء المفضل ليقوم فريقنا بجدولة الانطلاق وخطة التسليم.','terms'=>'ملاحظات تجارية','notes'=>'الأسعار بالدولار الكندي. تكاليف الأطراف الثالثة منفصلة ما لم يتم تضمينها صراحة. يبقى هذا العرض صالحاً حتى التاريخ الموضح أعلاه.','acceptance'=>'اعتماد العرض','acceptance_copy'=>'باعتماد هذا العرض، يؤكد العميل النطاق وخيارات الدعم ومدة العقد والإجماليات التجارية المبينة في هذه الوثيقة.','client_signature'=>'اسم العميل / التوقيع','date'=>'التاريخ','months'=>'أشهر','not_selected'=>'غير محدد','monthly_total'=>'إجمالي الخدمة الشهري','full_term'=>'مدة العقد الكاملة','one_time_label'=>'مرة واحدة','recurring_label'=>'متكرر','revision'=>'المراجعة'],
    'he'=>[
        'proposal'=>'הצעה מסחרית','prepared_for'=>'הוכן עבור','prepared_by'=>'הוכן על ידי','valid_until'=>'בתוקף עד','proposal_no'=>'מספר הצעה','investment'=>'ההשקעה שלך','one_time'=>'הקמה חד-פעמית','technical'=>'תמיכה טכנית','contract'=>'חוזה תמיכה','grand_total'=>'סהכ','overview'=>'תוכנית מחוברת להשקה, ביצוע וצמיחה מתמשכת.','scope'=>'היקף העבודה','scope_help'=>'כל השירותים, התוצרים וההתאמות המסחריות הכלולים בהצעה.','service'=>'שירות ותוצרים','list'=>'מחיר מחירון','adjustment'=>'התאמה','final'=>'מחיר סופי','section_total'=>'סכום הסעיף','support'=>'כיסוי והמשכיות','support_help'=>'הגנה טכנית ושירות שוטף המחושבים לפי התנאים המסחריים שנבחרו.','plan'=>'תוכנית','term'=>'תקופה','basis'=>'בסיס תמחור','amount'=>'סכום','monthly'=>'חודשי','contract_value'=>'שווי חוזה','commercial'=>'סיכום מסחרי','subtotal'=>'סכום לפני מס','tax'=>'HST','next_steps'=>'השלבים הבאים','next_steps_copy'=>'אשרו את ההצעה ואת מועד ההתחלה המועדף והצוות שלנו יתאם את ההשקה ותוכנית המסירה.','terms'=>'הערות מסחריות','notes'=>'המחירים בדולר קנדי. עלויות צד שלישי נפרדות אלא אם נכללו במפורש. ההצעה תקפה עד לתאריך המוצג לעיל.','acceptance'=>'אישור הצעה','acceptance_copy'=>'באישור הצעה זו, הלקוח מאשר את ההיקף, אפשרויות התמיכה, תקופת החוזה והסכומים המוצגים במסמך.','client_signature'=>'שם הלקוח / חתימה','date'=>'תאריך','months'=>'חודשים','not_selected'=>'לא נבחר','monthly_total'=>'סכום שירות חודשי','full_term'=>'תקופת חוזה מלאה','one_time_label'=>'חד-פעמי','recurring_label'=>'חוזר','revision'=>'גרסה'],
][$locale];
$money=static fn(mixed $value):string=>'$'.number_format((float)$value,2);
$setupItems=array_values(array_filter($quote['items'],static fn(array $item):bool=>in_array((string)($item['item_type']??'service'),['','service'],true)));
$technicalItems=array_values(array_filter($quote['items'],static fn(array $item):bool=>(string)($item['item_type']??'')==='support'));
$contractItems=array_values(array_filter($quote['items'],static fn(array $item):bool=>in_array((string)($item['item_type']??''),['support_contract','membership'],true)));
$technicalSubtotal=(float)($quote['support_amount']??array_sum(array_column($technicalItems,'total')));
$contractSubtotal=(float)($quote['membership_amount']??array_sum(array_column($contractItems,'total')));
$setupSubtotal=max(0,round((float)$quote['subtotal']-$technicalSubtotal-$contractSubtotal,2));
$contractDuration=max(0,(int)($quote['membership_duration_months']??0));
$contractMonthly=(float)($quote['membership_monthly_price']??0);
$supportDuration=max(0,(int)($quote['support_duration_months']??0));
$supportRate=(float)($quote['support_rate_percent']??0);
$supportCycles=$supportDuration>0?round($supportDuration/3,2):0;
$supportPackageName=$loc($quote,'support_package_name')?:((string)($quote['membership_name']??'')?:$labels['not_selected']);
$adjustment=static function(array $item,bool $contract=false):string{
    $discount=(float)($item['discount_percent']??0);
    $quantity=$contract?max(1,(int)($item['quantity']??1)):1;
    $final=(float)($item['total']??0)/$quantity;
    $list=(float)($item['unit_price']??0);
    if($discount>0){return '-'.rtrim(rtrim(number_format($discount,2),'0'),'.').'%';}
    return abs($list-$final)>.009?'Custom':'-';
};
$logoPath=public_path('assets/img/360-logo-final-exact.png');
$logoData=is_file($logoPath)?'data:image/png;base64,'.base64_encode((string)file_get_contents($logoPath)):'';
$createdAt=!empty($quote['created_at'])?date('M j, Y',strtotime((string)$quote['created_at'])):'-';
$validUntil=!empty($quote['valid_until'])?date('M j, Y',strtotime((string)$quote['valid_until'])):'-';
$packageName=$loc($quote,'package_name')?:ucfirst((string)($quote['selected_tier']??''));
$summary=trim((string)($quote['summary']??''))?:$labels['overview'];
$status=strtoupper(str_replace('_',' ',(string)($quote['status']??'draft')));
?>
<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= $rtl?'rtl':'ltr' ?>">
<head>
<meta charset="utf-8">
<style>
@page{margin:28px 34px 42px}*{box-sizing:border-box}html,body{margin:0;padding:0}body{font-family:"DejaVu Sans",Arial,sans-serif;color:#151515;font-size:9px;line-height:1.45;background:#fff}.page-footer{position:fixed;left:0;right:0;bottom:-27px;height:22px;border-top:1px solid #e6e1dc;color:#777;font-size:7px;padding-top:7px}.page-footer table{width:100%;border-collapse:collapse}.page-footer td:last-child{text-align:right;padding-right:48px}.hero{background:#090909;color:#fff;border-radius:14px;padding:29px 31px 27px}.hero-top,.hero-main,.identity,.snapshot,.chapter,.support-grid,.closing-grid{width:100%;border-collapse:collapse}.hero-top td{vertical-align:middle}.hero-top td:last-child{text-align:right}.logo{width:124px;height:auto}.kicker{color:#ff5315;font-size:8px;font-weight:700;letter-spacing:2.1px}.status-pill{display:inline-block;border:1px solid #7d2e12;color:#ff7a47;border-radius:20px;padding:5px 10px;font-size:7px;font-weight:700;letter-spacing:1px}.hero-main{margin-top:42px}.hero-main td{vertical-align:bottom}.hero-copy{width:68%}.hero-side{width:32%;text-align:right;border-left:1px solid #3b2118;padding-left:22px}.hero h1{font-size:31px;line-height:1.08;margin:8px 0 9px;letter-spacing:-1px}.hero h2{font-size:15px;font-weight:400;color:#dbd5d0;margin:0}.hero-side strong{display:block;color:#ff5315;font-size:17px;margin-top:5px}.hero-side small{display:block;color:#aaa;margin-top:6px}.identity{margin:18px 0 0;border:1px solid #e8e2dc;border-radius:10px}.identity td{width:25%;padding:13px 15px;vertical-align:top;border-right:1px solid #ebe7e3}.identity td:last-child{border-right:0}.label{display:block;color:#a24420;font-size:6.8px;font-weight:700;letter-spacing:1.2px;margin-bottom:5px}.identity strong{display:block;font-size:9px}.identity small{display:block;color:#6d6965;margin-top:3px}.intro{padding:22px 3px 16px}.intro h3{font-size:17px;margin:0 0 7px}.intro p{font-size:10px;color:#5e5955;margin:0;max-width:90%}.snapshot{border-spacing:8px 0;margin:0 -8px;width:calc(100% + 16px)}.snapshot td{width:25%;padding:13px 14px;background:#f7f5f2;border-top:3px solid #ded7d0;border-radius:8px;vertical-align:top}.snapshot td.grand-card{background:#fff0e9;border-top-color:#ff5315}.snapshot span{display:block;color:#756e68;font-size:6.8px;font-weight:700;letter-spacing:.8px}.snapshot strong{display:block;font-size:15px;margin-top:5px}.snapshot small{display:block;color:#857c76;margin-top:4px}.roadmap{margin-top:18px;padding:12px 15px;background:#111;color:#fff;border-radius:8px}.roadmap table{width:100%;border-collapse:collapse}.roadmap td{width:33.33%;padding:0 11px;border-right:1px solid #3a302b}.roadmap td:last-child{border-right:0}.roadmap b{color:#ff6630;font-size:12px;margin-right:5px}.roadmap strong{font-size:8px}.roadmap small{display:block;color:#aaa;margin-top:3px}.page-break{page-break-before:always}.section-kicker{color:#ff5315;font-size:7px;font-weight:700;letter-spacing:1.8px}.chapter{margin-bottom:14px}.chapter td{vertical-align:bottom}.chapter td:last-child{text-align:right}.chapter h2{font-size:23px;line-height:1.1;margin:6px 0 4px}.chapter p{margin:0;color:#6c6661;font-size:9px}.chapter .chapter-number{display:inline-block;width:32px;height:32px;padding-top:8px;text-align:center;border-radius:50%;background:#ff5315;color:#fff;font-weight:700;font-size:10px}.chapter-total{font-size:18px}.chapter-total small{display:block;color:#837b75;font-size:7px;font-weight:400;letter-spacing:.8px}.scope-table{width:100%;border-collapse:collapse;border:1px solid #e7e2dd}.scope-table thead{display:table-header-group}.scope-table th{background:#111;color:#fff;padding:9px 8px;font-size:6.6px;letter-spacing:.8px;text-align:left}.scope-table th.money-col,.scope-table td.money-col{text-align:right}.scope-table td{padding:11px 8px;border-bottom:1px solid #e9e5e1;vertical-align:top}.scope-table tr{page-break-inside:avoid}.scope-table .index{width:5%;color:#ff5315;font-weight:700}.scope-table .service-col{width:51%}.scope-table .money-col{width:14%}.service-title{font-size:10px;font-weight:700}.service-desc{display:block;color:#68625e;font-size:7.5px;margin-top:3px}.type-tag{display:inline-block;margin-top:5px;padding:2px 6px;background:#fff0e9;color:#a13a13;border-radius:9px;font-size:6px;font-weight:700;letter-spacing:.6px}.work-list{margin:7px 0 0;padding:0;list-style:none}.work-list li{margin:0 0 4px;padding-left:10px;position:relative;color:#393633;font-size:7px}.work-list li:before{content:"";position:absolute;left:0;top:3px;width:4px;height:4px;border-radius:50%;background:#ff5315}.work-list small{display:block;color:#77716d}.table-total td{background:#f5f2ef;border-bottom:0;font-weight:700;font-size:10px}.table-total td:last-child{text-align:right;color:#e94308;font-size:12px}.support-card{border:1px solid #e6e0da;border-radius:10px;margin-bottom:12px;page-break-inside:avoid;overflow:hidden}.support-card-title{background:#111;color:#fff;padding:11px 15px}.support-card-title table{width:100%;border-collapse:collapse}.support-card-title td:last-child{text-align:right;font-size:14px;font-weight:700;color:#ff6a32}.support-card-title strong{font-size:11px}.support-card-title small{display:block;color:#aaa;margin-top:2px}.support-grid td{width:25%;padding:10px 14px;vertical-align:top;border-right:1px solid #ece7e3}.support-grid td:last-child{border-right:0}.support-grid strong{display:block;font-size:9px;margin-top:5px}.support-grid p{margin:4px 0 0;color:#6d6762;font-size:7px}.contract-table{width:100%;border-collapse:collapse}.contract-table th{background:#f5f2ef;color:#6d6660;font-size:6.4px;letter-spacing:.7px;text-align:left;padding:7px 8px}.contract-table th:last-child,.contract-table td:last-child{text-align:right}.contract-table td{padding:8px;border-top:1px solid #e9e4df;vertical-align:top}.contract-table strong{display:block}.contract-table small{color:#706a65}.commercial{width:100%;border-collapse:collapse;margin-top:12px;page-break-inside:avoid}.commercial>tbody>tr>td{vertical-align:top}.commercial-left{width:52%;padding-right:24px}.commercial-right{width:48%;background:#111;color:#fff;padding:14px 18px;border-radius:10px}.note-box{border-left:3px solid #ff5315;background:#fff3ed;padding:10px 14px;margin-bottom:8px}.note-box h3{margin:0 0 4px;font-size:10px}.note-box p{margin:0;color:#625b56;font-size:7.5px}.totals{width:100%;border-collapse:collapse}.totals td{padding:4px 0;border-bottom:1px solid #34302d}.totals td:last-child{text-align:right;font-weight:700}.totals .minor{color:#c6bfba}.totals .grand td{padding-top:9px;border:0;font-size:14px;color:#ff6a32}.acceptance{margin-top:11px;border:1px solid #e4ded8;border-radius:10px;padding:10px 14px;page-break-inside:avoid}.acceptance h3{margin:0 0 4px;font-size:11px}.acceptance p{margin:0;color:#65605c;font-size:7px}.signature-table{width:100%;border-collapse:collapse;margin-top:14px}.signature-table td{width:50%;padding-right:22px}.signature-line{border-top:1px solid #777;padding-top:4px;color:#777;font-size:6.2px;letter-spacing:.7px}.closing{margin-top:7px;background:#ff5315;color:#fff;border-radius:7px;padding:6px 11px;page-break-inside:avoid}.closing-grid td{vertical-align:middle}.closing-grid td:last-child{text-align:right}.closing h3{font-size:9px;margin:0}.closing p{display:none}.closing strong{display:block;font-size:7px}.closing small{display:block;color:#ffe4d7;margin-top:1px;font-size:5.8px}.muted{color:#777}.rtl{text-align:right}body[dir="rtl"] .hero-side{border-left:0;border-right:1px solid #3b2118;padding-left:0;padding-right:22px}body[dir="rtl"] .scope-table th{text-align:right}body[dir="rtl"] .scope-table th.money-col,body[dir="rtl"] .scope-table td.money-col{text-align:left}body[dir="rtl"] .work-list li{padding-left:0;padding-right:10px}body[dir="rtl"] .work-list li:before{left:auto;right:0}body[dir="rtl"] .commercial-left{padding-right:0;padding-left:24px}
</style>
</head>
<body dir="<?= $rtl?'rtl':'ltr' ?>">
<div class="page-footer"><table><tr><td><?= e($company['name']) ?> &nbsp; | &nbsp; <?= e($quote['proposal_number']) ?> &nbsp; | &nbsp; Confidential</td><td></td></tr></table></div>

<section class="cover">
    <div class="hero">
        <table class="hero-top"><tr><td><?php if($logoData): ?><img class="logo" src="<?= $logoData ?>" alt="360 Creative Agency"><?php else: ?><strong>360 CREATIVE AGENCY</strong><?php endif; ?></td><td><span class="status-pill"><?= e($status) ?></span></td></tr></table>
        <table class="hero-main"><tr><td class="hero-copy"><span class="kicker"><?= e($labels['proposal']) ?></span><h1><?= e($quote['business_name']) ?></h1><h2><?= e($packageName) ?> - built for a confident launch and measurable growth.</h2></td><td class="hero-side"><span class="kicker"><?= e($labels['investment']) ?></span><strong><?= e($money($quote['total'])) ?></strong><small>CAD - including HST</small></td></tr></table>
    </div>

    <table class="identity"><tr>
        <td><span class="label"><?= e($labels['prepared_for']) ?></span><strong><?= e($quote['contact_name']) ?></strong><small><?= e($quote['contact_email']) ?></small><?php if(!empty($quote['contact_phone'])): ?><small><?= e($quote['contact_phone']) ?></small><?php endif; ?></td>
        <td><span class="label"><?= e($labels['prepared_by']) ?></span><strong><?= e($company['name']) ?></strong><small><?= e($company['email']) ?></small><small><?= e($company['phone']) ?></small></td>
        <td><span class="label"><?= e($labels['proposal_no']) ?></span><strong><?= e($quote['proposal_number']) ?></strong><small><?= e($labels['revision']) ?> <?= (int)($quote['revision']??1) ?> - <?= e($createdAt) ?></small></td>
        <td><span class="label"><?= e($labels['valid_until']) ?></span><strong><?= e($validUntil) ?></strong><small>CAD - Canadian Dollar</small></td>
    </tr></table>

    <div class="intro"><span class="section-kicker">THE PLAN</span><h3><?= e($labels['overview']) ?></h3><p><?= e($summary) ?></p></div>

    <table class="snapshot"><tr>
        <td><span><?= e($labels['one_time']) ?></span><strong><?= e($money($setupSubtotal)) ?></strong><small><?= count($setupItems) ?> selected service<?= count($setupItems)===1?'':'s' ?></small></td>
        <td><span><?= e($labels['technical']) ?></span><strong><?= e($money($technicalSubtotal)) ?></strong><small><?= $supportDuration>0?e($supportDuration.' '.$labels['months']):e($labels['not_selected']) ?></small></td>
        <td><span><?= e($labels['contract']) ?></span><strong><?= e($money($contractSubtotal)) ?></strong><small><?= $contractDuration>0?e($money($contractMonthly).'/mo x '.$contractDuration.' '.$labels['months']):e($labels['not_selected']) ?></small></td>
        <td class="grand-card"><span><?= e($labels['grand_total']) ?></span><strong><?= e($money($quote['total'])) ?></strong><small>Includes <?= e(rtrim(rtrim(number_format((float)$quote['tax_percent'],2),'0'),'.')) ?>% HST</small></td>
    </tr></table>

    <div class="roadmap"><table><tr>
        <td><b>01</b><strong><?= e($labels['one_time']) ?></strong><small>Defined services and deliverables</small></td>
        <td><b>02</b><strong><?= e($labels['technical']) ?></strong><small>Coverage tied to setup value</small></td>
        <td><b>03</b><strong><?= e($labels['contract']) ?></strong><small>Monthly delivery across the term</small></td>
    </tr></table></div>
</section>

<section class="page-break">
    <table class="chapter"><tr><td><span class="section-kicker">01 - <?= e($labels['one_time_label']) ?></span><h2><?= e($labels['scope']) ?></h2><p><?= e($labels['scope_help']) ?></p></td><td><span class="chapter-number">01</span><strong class="chapter-total"><small><?= e($labels['section_total']) ?></small><?= e($money($setupSubtotal)) ?></strong></td></tr></table>
    <table class="scope-table">
        <thead><tr><th>#</th><th><?= e($labels['service']) ?></th><th class="money-col"><?= e($labels['list']) ?></th><th class="money-col"><?= e($labels['adjustment']) ?></th><th class="money-col"><?= e($labels['final']) ?></th></tr></thead>
        <tbody>
        <?php foreach($setupItems as $index=>$item): ?>
            <tr><td class="index"><?= $index+1 ?></td><td class="service-col"><span class="service-title"><?= e($loc($item,'title')) ?></span><?php if($loc($item,'description')): ?><span class="service-desc"><?= e($loc($item,'description')) ?></span><?php endif; ?><span class="type-tag"><?= e($labels['one_time_label']) ?></span><?php if(!empty($item['work_items'])): ?><ul class="work-list"><?php foreach($item['work_items'] as $workItem): ?><?php $scope=$loc($workItem,'scope_value'); ?><li><strong><?= e($loc($workItem,'title')) ?></strong><?php if($scope): ?><small><?= e($scope) ?></small><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?></td><td class="money-col"><?= e($money($item['unit_price'])) ?></td><td class="money-col"><?= e($adjustment($item)) ?></td><td class="money-col"><strong><?= e($money($item['total'])) ?></strong></td></tr>
        <?php endforeach; ?>
        <?php if(!$setupItems): ?><tr><td colspan="5" class="muted"><?= e($labels['not_selected']) ?></td></tr><?php endif; ?>
        <tr class="table-total"><td colspan="4"><?= e($labels['section_total']) ?></td><td><?= e($money($setupSubtotal)) ?></td></tr>
        </tbody>
    </table>
</section>

<section class="page-break">
    <table class="chapter"><tr><td><span class="section-kicker">02 + 03 - CONTINUITY</span><h2><?= e($labels['support']) ?></h2><p><?= e($labels['support_help']) ?></p></td><td><span class="chapter-number">02</span></td></tr></table>

    <article class="support-card">
        <div class="support-card-title"><table><tr><td><strong><?= e($labels['technical']) ?></strong><small>Protection after setup delivery</small></td><td><?= e($money($technicalSubtotal)) ?></td></tr></table></div>
        <table class="support-grid"><tr>
            <td><span class="label"><?= e($labels['plan']) ?></span><strong><?= e((string)($quote['support_name']??'')?:$labels['not_selected']) ?></strong><p><?= e($loc($technicalItems[0]??[],'description')) ?></p></td>
            <td><span class="label"><?= e($labels['term']) ?></span><strong><?= $supportDuration>0?e($supportDuration.' '.$labels['months']):e($labels['not_selected']) ?></strong></td>
            <td><span class="label"><?= e($labels['basis']) ?></span><strong><?= $technicalSubtotal>0?e($money($setupSubtotal).' x '.rtrim(rtrim(number_format($supportRate,2),'0'),'.').'% x '.$supportCycles):'-' ?></strong></td>
            <td><span class="label"><?= e($labels['amount']) ?></span><strong><?= e($money($technicalSubtotal)) ?></strong></td>
        </tr></table>
    </article>

    <article class="support-card">
        <div class="support-card-title"><table><tr><td><strong><?= e($labels['contract']) ?> - <?= e($supportPackageName) ?></strong><small><?= $contractDuration>0?e($money($contractMonthly).'/month for '.$contractDuration.' '.$labels['months']):e($labels['not_selected']) ?></small></td><td><?= e($money($contractSubtotal)) ?></td></tr></table></div>
        <?php if($contractItems): ?>
        <table class="contract-table"><thead><tr><th><?= e($labels['service']) ?></th><th><?= e($labels['monthly']) ?></th><th><?= e($labels['term']) ?></th><th><?= e($labels['contract_value']) ?></th></tr></thead><tbody>
            <?php foreach($contractItems as $item): ?><?php $quantity=max(1,(int)($item['quantity']??($contractDuration?:1)));$monthlyFinal=(float)$item['total']/$quantity; ?>
            <tr><td><strong><?= e($loc($item,'title')) ?></strong><small><?= e($loc($item,'description')) ?></small><?php if(!empty($item['work_items'])): ?><ul class="work-list"><?php foreach($item['work_items'] as $workItem): ?><li><strong><?= e($loc($workItem,'title')) ?></strong><?php if($loc($workItem,'scope_value')): ?><small><?= e($loc($workItem,'scope_value')) ?></small><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?></td><td><?= e($money($monthlyFinal)) ?></td><td><?= (int)$quantity ?> <?= e($labels['months']) ?></td><td><strong><?= e($money($item['total'])) ?></strong></td></tr>
            <?php endforeach; ?>
        </tbody></table>
        <table class="support-grid"><tr><td><span class="label"><?= e($labels['monthly_total']) ?></span><strong><?= e($money($contractMonthly)) ?></strong></td><td><span class="label"><?= e($labels['full_term']) ?></span><strong><?= (int)$contractDuration ?> <?= e($labels['months']) ?></strong></td><td><span class="label"><?= e($labels['contract_value']) ?></span><strong><?= e($money($contractSubtotal)) ?></strong></td><td><span class="label">DELIVERY TYPE</span><strong><?= e($labels['recurring_label']) ?></strong></td></tr></table>
        <?php else: ?><table class="support-grid"><tr><td><span class="label"><?= e($labels['contract']) ?></span><strong><?= e($labels['not_selected']) ?></strong></td></tr></table><?php endif; ?>
    </article>

    <table class="commercial"><tr><td class="commercial-left">
        <div class="note-box"><h3><?= e($labels['next_steps']) ?></h3><p><?= e($labels['next_steps_copy']) ?></p></div>
        <div class="note-box"><h3><?= e($labels['terms']) ?></h3><p><?= e($labels['notes']) ?></p></div>
    </td><td class="commercial-right"><span class="kicker"><?= e($labels['commercial']) ?></span><table class="totals">
        <tr><td class="minor"><?= e($labels['one_time']) ?></td><td><?= e($money($setupSubtotal)) ?></td></tr>
        <tr><td class="minor"><?= e($labels['technical']) ?></td><td><?= e($money($technicalSubtotal)) ?></td></tr>
        <tr><td class="minor"><?= e($labels['contract']) ?></td><td><?= e($money($contractSubtotal)) ?></td></tr>
        <tr><td><?= e($labels['subtotal']) ?></td><td><?= e($money($quote['subtotal'])) ?></td></tr>
        <tr><td><?= e($labels['tax']) ?> <?= e(rtrim(rtrim(number_format((float)$quote['tax_percent'],2),'0'),'.')) ?>%</td><td><?= e($money($quote['tax'])) ?></td></tr>
        <tr class="grand"><td><?= e($labels['grand_total']) ?></td><td><?= e($money($quote['total'])) ?></td></tr>
    </table></td></tr></table>

    <div class="acceptance"><h3><?= e($labels['acceptance']) ?></h3><p><?= e($labels['acceptance_copy']) ?></p><table class="signature-table"><tr><td><div class="signature-line"><?= e($labels['client_signature']) ?></div></td><td><div class="signature-line"><?= e($labels['date']) ?></div></td></tr></table></div>
    <div class="closing"><table class="closing-grid"><tr><td><h3>Ready when you are.</h3><p>Let us turn the approved scope into a clear, accountable delivery plan.</p></td><td><strong><?= e($company['name']) ?></strong><small><?= e($company['website']) ?> - <?= e($company['phone']) ?></small><small><?= e($company['address']) ?></small></td></tr></table></div>
</section>
</body>
</html>
