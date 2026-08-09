<?php

declare(strict_types=1);

return [
    'sections' => ['message' => 'الرسالة', 'workflow' => 'سير المراجعة'],
    'statuses' => ['new' => 'جديد', 'in_review' => 'قيد المراجعة', 'resolved' => 'تمت المعالجة', 'closed' => 'مغلق'],
    'actions' => ['mark_unread' => 'تحديد كغير مقروء', 'assign_to_me' => 'تعييني كمراجع', 'internal_notes' => 'ملاحظات داخلية', 'start_review' => 'بدء المراجعة', 'resolve' => 'تحديد كمعالج', 'close' => 'إغلاق'],
    'notifications' => ['marked_unread' => 'تم تحديد الرسالة كغير مقروءة', 'assigned' => 'تم تكليفك بالرسالة', 'notes_saved' => 'تم حفظ الملاحظات الداخلية', 'transitioned' => 'تم تحديث حالة الرسالة', 'failed' => 'لم يتم تحديث الرسالة'],
    'fields' => ['reference' => 'رقم المرجع', 'assigned_to' => 'المراجع المكلف', 'internal_notes' => 'ملاحظات داخلية', 'status' => 'الحالة', 'submitted' => 'تاريخ التقديم', 'email_delivery' => 'تسليم البريد'],
    'values' => ['unassigned' => 'غير مكلف', 'none' => 'لا يوجد'],
];
