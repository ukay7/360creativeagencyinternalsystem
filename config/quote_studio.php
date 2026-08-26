<?php

return [
    'company' => [
        'name' => '360 Creative Agency',
        'website' => 'www.360creativeagency.ca',
        'phone' => '+1 (416) 836 7311',
        'email' => 'info@360creativeagency.ca',
        'address' => '87 Dickson Hill Rd, Markham, ON, L3P 3J3',
    ],
    'locales' => [
        'en' => ['label' => 'English', 'dir' => 'ltr'],
        'ar' => ['label' => 'العربية', 'dir' => 'rtl'],
        'he' => ['label' => 'עברית', 'dir' => 'rtl'],
    ],
    'recommendation' => [
        'basic_max' => 7,
        'medium_max' => 14,
        'max_score' => 20,
        'presets' => [
            'basic' => 0,
            'medium' => 1,
            'pro' => 2,
        ],
    ],
    'assessment' => [
        [
            'key' => 'employees',
            'label' => 'How many employees does the business have?',
            'label_ar' => 'كم عدد موظفي الشركة؟',
            'label_he' => 'כמה עובדים יש לעסק?',
            'options' => [
                ['value'=>'1_2','label'=>'1 to 2','label_ar'=>'1 إلى 2','label_he'=>'1 עד 2','score'=>0],
                ['value'=>'3_10','label'=>'3 to 10','label_ar'=>'3 إلى 10','label_he'=>'3 עד 10','score'=>1],
                ['value'=>'11_plus','label'=>'11 or more','label_ar'=>'11 أو أكثر','label_he'=>'11 ומעלה','score'=>2],
            ],
        ],
        [
            'key' => 'locations',
            'label' => 'How many locations does it operate?',
            'label_ar' => 'كم عدد المواقع التي تعمل فيها؟',
            'label_he' => 'בכמה מיקומים העסק פועל?',
            'options' => [
                ['value'=>'1','label'=>'1','label_ar'=>'1','label_he'=>'1','score'=>0],
                ['value'=>'2_3','label'=>'2 to 3','label_ar'=>'2 إلى 3','label_he'=>'2 עד 3','score'=>1],
                ['value'=>'4_plus','label'=>'4 or more','label_ar'=>'4 أو أكثر','label_he'=>'4 ומעלה','score'=>2],
            ],
        ],
        [
            'key' => 'monthly_customers',
            'label' => 'How many monthly customers or orders?',
            'label_ar' => 'كم عدد العملاء أو الطلبات شهرياً؟',
            'label_he' => 'כמה לקוחות או הזמנות יש בחודש?',
            'options' => [
                ['value'=>'up_to_50','label'=>'Up to 50','label_ar'=>'حتى 50','label_he'=>'עד 50','score'=>0],
                ['value'=>'51_250','label'=>'51 to 250','label_ar'=>'51 إلى 250','label_he'=>'51 עד 250','score'=>1],
                ['value'=>'251_plus','label'=>'251 or more','label_ar'=>'251 أو أكثر','label_he'=>'251 ומעלה','score'=>2],
            ],
        ],
        [
            'key' => 'reach',
            'label' => 'What is the business reach?',
            'label_ar' => 'ما هو نطاق عمل الشركة؟',
            'label_he' => 'מהו טווח הפעילות של העסק?',
            'options' => [
                ['value'=>'local','label'=>'Local','label_ar'=>'محلي','label_he'=>'מקומי','score'=>0],
                ['value'=>'regional','label'=>'Regional','label_ar'=>'إقليمي','label_he'=>'אזורי','score'=>1],
                ['value'=>'national','label'=>'National / international','label_ar'=>'وطني / دولي','label_he'=>'ארצי / בינלאומי','score'=>2],
            ],
        ],
        [
            'key' => 'website_complexity',
            'label' => 'What website complexity is needed?',
            'label_ar' => 'ما مستوى تعقيد الموقع المطلوب؟',
            'label_he' => 'איזו מורכבות אתר נדרשת?',
            'options' => [
                ['value'=>'information','label'=>'Information website','label_ar'=>'موقع تعريفي','label_he'=>'אתר מידע','score'=>0],
                ['value'=>'lead_generation','label'=>'Lead-generation website','label_ar'=>'موقع لتوليد العملاء','label_he'=>'אתר ליצירת לידים','score'=>1],
                ['value'=>'commerce_custom','label'=>'E-commerce or custom platform','label_ar'=>'متجر إلكتروني أو منصة مخصصة','label_he'=>'מסחר אלקטרוני או מערכת מותאמת','score'=>2],
            ],
        ],
        [
            'key' => 'platforms',
            'label' => 'How many digital platforms are needed?',
            'label_ar' => 'كم منصة رقمية مطلوبة؟',
            'label_he' => 'כמה פלטפורמות דיגיטליות נדרשות?',
            'options' => [
                ['value'=>'1','label'=>'1','label_ar'=>'1','label_he'=>'1','score'=>0],
                ['value'=>'2_3','label'=>'2 to 3','label_ar'=>'2 إلى 3','label_he'=>'2 עד 3','score'=>1],
                ['value'=>'4_plus','label'=>'4 or more','label_ar'=>'4 أو أكثر','label_he'=>'4 ומעלה','score'=>2],
            ],
        ],
        [
            'key' => 'integrations',
            'label' => 'How many system integrations are required?',
            'label_ar' => 'كم عدد تكاملات الأنظمة المطلوبة؟',
            'label_he' => 'כמה אינטגרציות מערכת נדרשות?',
            'options' => [
                ['value'=>'none','label'=>'None','label_ar'=>'لا يوجد','label_he'=>'ללא','score'=>0],
                ['value'=>'1_2','label'=>'1 to 2','label_ar'=>'1 إلى 2','label_he'=>'1 עד 2','score'=>1],
                ['value'=>'3_plus','label'=>'3 or more','label_ar'=>'3 أو أكثر','label_he'=>'3 ומעלה','score'=>2],
            ],
        ],
        [
            'key' => 'monthly_content',
            'label' => 'How much monthly content is expected?',
            'label_ar' => 'ما حجم المحتوى الشهري المتوقع؟',
            'label_he' => 'כמה תוכן חודשי נדרש?',
            'options' => [
                ['value'=>'up_to_10','label'=>'Up to 10 items','label_ar'=>'حتى 10 عناصر','label_he'=>'עד 10 פריטים','score'=>0],
                ['value'=>'11_25','label'=>'11 to 25 items','label_ar'=>'11 إلى 25 عنصراً','label_he'=>'11 עד 25 פריטים','score'=>1],
                ['value'=>'26_plus','label'=>'26 or more items','label_ar'=>'26 عنصراً أو أكثر','label_he'=>'26 פריטים ומעלה','score'=>2],
            ],
        ],
        [
            'key' => 'professional_media',
            'label' => 'How often is professional media needed?',
            'label_ar' => 'كم مرة تحتاج الشركة إلى إنتاج إعلامي احترافي؟',
            'label_he' => 'באיזו תדירות נדרשת הפקת מדיה מקצועית?',
            'options' => [
                ['value'=>'occasionally','label'=>'Occasionally','label_ar'=>'أحياناً','label_he'=>'מדי פעם','score'=>0],
                ['value'=>'monthly','label'=>'Monthly','label_ar'=>'شهرياً','label_he'=>'חודשי','score'=>1],
                ['value'=>'weekly','label'=>'Weekly / high volume','label_ar'=>'أسبوعياً / حجم كبير','label_he'=>'שבועי / נפח גבוה','score'=>2],
            ],
        ],
        [
            'key' => 'approvers',
            'label' => 'How many people approve the work?',
            'label_ar' => 'كم شخصاً يعتمد العمل؟',
            'label_he' => 'כמה אנשים מאשרים את העבודה?',
            'options' => [
                ['value'=>'1','label'=>'1','label_ar'=>'1','label_he'=>'1','score'=>0],
                ['value'=>'2_3','label'=>'2 to 3','label_ar'=>'2 إلى 3','label_he'=>'2 עד 3','score'=>1],
                ['value'=>'4_plus','label'=>'4 or more','label_ar'=>'4 أو أكثر','label_he'=>'4 ומעלה','score'=>2],
            ],
        ],
    ],
];
