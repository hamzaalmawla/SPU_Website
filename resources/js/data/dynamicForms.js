export const dynamicForms = {
    'conference-registration': {
        titleEn: 'Conference Registration',
        titleAr: 'التسجيل في المؤتمر',
        submitLabelEn: 'Submit Registration',
        submitLabelAr: 'إرسال التسجيل',
        fields: [
            { name: 'fullName', type: 'text', labelEn: 'Full Name', labelAr: 'الاسم الكامل', placeholderEn: 'Enter your full name', placeholderAr: 'أدخل اسمك الكامل', required: true },
            { name: 'email', type: 'email', labelEn: 'Email Address', labelAr: 'البريد الإلكتروني', placeholderEn: 'you@example.com', placeholderAr: 'بريدك@الإلكتروني.com', required: true },
            { name: 'phone', type: 'tel', labelEn: 'Phone Number', labelAr: 'رقم الهاتف', placeholderEn: '+963 xxx xxx xxx', placeholderAr: '+963 xxx xxx xxx', required: false },
            { name: 'affiliation', type: 'text', labelEn: 'Institution / Affiliation', labelAr: 'المؤسسة / الانتماء', placeholderEn: 'University or organization', placeholderAr: 'الجامعة أو المؤسسة', required: true },
            { name: 'role', type: 'select', labelEn: 'Attendance Role', labelAr: 'دور الحضور', required: true, options: [
                { value: '', labelEn: 'Select your role', labelAr: 'اختر دورك' },
                { value: 'attendee', labelEn: 'Attendee', labelAr: 'حاضر' },
                { value: 'presenter', labelEn: 'Presenter', labelAr: 'مقدم ورقة' },
                { value: 'poster', labelEn: 'Poster Session', labelAr: 'جلسة ملصقات' },
                { value: 'moderator', labelEn: 'Moderator / Panelist', labelAr: 'مشرف' },
            ] },
            { name: 'dietary', type: 'select', labelEn: 'Dietary Requirements', labelAr: 'متطلبات النظام الغذائي', required: false, options: [
                { value: '', labelEn: 'None', labelAr: 'لا شيء' },
                { value: 'vegetarian', labelEn: 'Vegetarian', labelAr: 'نباتي' },
                { value: 'vegan', labelEn: 'Vegan', labelAr: 'نباتي صرف' },
                { value: 'halal', labelEn: 'Halal only', labelAr: 'حلال فقط' },
                { value: 'other', labelEn: 'Other (specify)', labelAr: 'أخرى (حدد)' },
            ] },
            { name: 'specialNeeds', type: 'textarea', labelEn: 'Special Requirements or Notes', labelAr: 'متطلبات خاصة أو ملاحظات', placeholderEn: 'Any accessibility needs or special requests...', placeholderAr: 'أي احتياجات وصول أو طلبات خاصة...', required: false },
        ],
    },
    'symposium-registration': {
        titleEn: 'Symposium Registration',
        titleAr: 'التسجيل في الندوة',
        submitLabelEn: 'Register Now',
        submitLabelAr: 'سجل الآن',
        fields: [
            { name: 'fullName', type: 'text', labelEn: 'Full Name', labelAr: 'الاسم الكامل', placeholderEn: 'Enter your full name', placeholderAr: 'أدخل اسمك الكامل', required: true },
            { name: 'email', type: 'email', labelEn: 'Email Address', labelAr: 'البريد الإلكتروني', placeholderEn: 'you@example.com', placeholderAr: 'بريدك@الإلكتروني.com', required: true },
            { name: 'phone', type: 'tel', labelEn: 'Phone Number', labelAr: 'رقم الهاتف', placeholderEn: '+963 xxx xxx xxx', placeholderAr: '+963 xxx xxx xxx', required: false },
            { name: 'department', type: 'text', labelEn: 'Department / Faculty', labelAr: 'القسم / الكلية', placeholderEn: 'Your academic department', placeholderAr: 'قسمك الأكاديمي', required: true },
            { name: 'year', type: 'select', labelEn: 'Academic Year', labelAr: 'السنة الأكاديمية', required: true, options: [
                { value: '', labelEn: 'Select year', labelAr: 'اختر السنة' },
                { value: '1', labelEn: 'First Year', labelAr: 'السنة الأولى' },
                { value: '2', labelEn: 'Second Year', labelAr: 'السنة الثانية' },
                { value: '3', labelEn: 'Third Year', labelAr: 'السنة الثالثة' },
                { value: '4', labelEn: 'Fourth Year', labelAr: 'السنة الرابعة' },
                { value: '5', labelEn: 'Fifth Year', labelAr: 'السنة الخامسة' },
                { value: 'master', labelEn: 'Master\'s Student', labelAr: 'طالب ماجستير' },
                { value: 'phd', labelEn: 'PhD Student', labelAr: 'طالب دكتوراه' },
                { value: 'faculty', labelEn: 'Faculty Member', labelAr: 'عضو هيئة تدريس' },
                { value: 'external', labelEn: 'External Attendee', labelAr: 'حاضر خارجي' },
            ] },
            { name: 'specialNeeds', type: 'textarea', labelEn: 'Special Requirements or Notes', labelAr: 'متطلبات خاصة أو ملاحظات', placeholderEn: 'Any accessibility needs or special requests...', placeholderAr: 'أي احتياجات وصول أو طلبات خاصة...', required: false },
        ],
    },
    'activity-registration': {
        titleEn: 'Activity Registration',
        titleAr: 'التسجيل في النشاط',
        submitLabelEn: 'Submit',
        submitLabelAr: 'إرسال',
        fields: [
            { name: 'fullName', type: 'text', labelEn: 'Full Name', labelAr: 'الاسم الكامل', placeholderEn: 'Enter your full name', placeholderAr: 'أدخل اسمك الكامل', required: true },
            { name: 'email', type: 'email', labelEn: 'Email Address', labelAr: 'البريد الإلكتروني', placeholderEn: 'you@example.com', placeholderAr: 'بريدك@الإلكتروني.com', required: true },
            { name: 'phone', type: 'tel', labelEn: 'Phone Number', labelAr: 'رقم الهاتف', placeholderEn: '+963 xxx xxx xxx', placeholderAr: '+963 xxx xxx xxx', required: false },
            { name: 'studentId', type: 'text', labelEn: 'Student ID', labelAr: 'رقم الطالب', placeholderEn: 'e.g. 2024001', placeholderAr: 'مثال: 2024001', required: false },
            { name: 'notes', type: 'textarea', labelEn: 'Notes', labelAr: 'ملاحظات', placeholderEn: 'Any additional information...', placeholderAr: 'أي معلومات إضافية...', required: false },
        ],
    },
    'job-application': {
        titleEn: 'Job Application',
        titleAr: 'التقديم على الوظيفة',
        submitLabelEn: 'Submit Application',
        submitLabelAr: 'إرسال الطلب',
        multiStep: true,
        steps: [
            { titleEn: 'Personal Information', titleAr: 'المعلومات الشخصية', fields: [
                { name: 'firstNameAr', type: 'text', labelEn: 'First Name (Arabic)', labelAr: 'الاسم الأول بالعربية', required: true },
                { name: 'lastNameAr', type: 'text', labelEn: 'Last Name (Arabic)', labelAr: 'الاسم الأخير بالعربية كما ورد في جواز السفر', required: true },
                { name: 'email', type: 'email', labelEn: 'Email Address', labelAr: 'البريد الإلكتروني', required: true },
                { name: 'phone', type: 'tel', labelEn: 'Mobile Phone', labelAr: 'رقم الهاتف المحمول', required: true },
                { name: 'gender', type: 'select', labelEn: 'Gender', labelAr: 'الجنس', required: true, options: [{ value: '', labelEn: 'Select gender', labelAr: 'اختر الجنس' }, { value: 'male', labelEn: 'Male', labelAr: 'ذكر' }, { value: 'female', labelEn: 'Female', labelAr: 'أنثى' }] },
                { name: 'profession', type: 'text', labelEn: 'Profession', labelAr: 'المهنة', required: true },
                { name: 'birthDate', type: 'date', labelEn: 'Date of Birth', labelAr: 'تاريخ الميلاد', required: true },
                { name: 'address', type: 'text', labelEn: 'Detailed Address', labelAr: 'العنوان بالتفصيل', required: false },
            ] },
            { titleEn: 'Academic Qualifications', titleAr: 'المؤهلات العلمية', fields: [
                { name: 'educationLevel', type: 'select', labelEn: 'Education Level', labelAr: 'المستوى التعليمي', required: true, options: [{ value: '', labelEn: 'Select education level', labelAr: 'اختر المستوى التعليمي' }, { value: 'phd', labelEn: 'PhD', labelAr: 'دكتوراه' }, { value: 'master', labelEn: 'Master\'s', labelAr: 'ماجستير' }, { value: 'bachelor', labelEn: 'Bachelor\'s', labelAr: 'بكالوريوس' }, { value: 'institute', labelEn: 'Institute', labelAr: 'معهد' }, { value: 'none', labelEn: 'Not enrolled', labelAr: 'غير مدخول' }] },
                { name: 'highestUniversity', type: 'text', labelEn: 'Highest University Attended', labelAr: 'الجامعة المنارة لأعلى دفعة علمية', required: true },
                { name: 'academicExperience', type: 'number', labelEn: 'Academic Experience (Years)', labelAr: 'سنوات الخبرة الأكاديمية', placeholderEn: '0', placeholderAr: '0', required: false },
                { name: 'englishLevel', type: 'select', labelEn: 'English Language Level', labelAr: 'مستوى اللغة الإنجليزية', required: true, options: [{ value: '', labelEn: 'Select level', labelAr: 'اختر المستوى' }, { value: 'native', labelEn: 'Native', labelAr: 'لغة أم' }, { value: 'fluent', labelEn: 'Fluent', labelAr: 'طلاقة' }, { value: 'advanced', labelEn: 'Advanced', labelAr: 'متقدم' }, { value: 'intermediate', labelEn: 'Intermediate', labelAr: 'متوسط' }, { value: 'basic', labelEn: 'Basic', labelAr: 'أساسي' }] },
                { name: 'personalSkills', type: 'select', labelEn: 'Personal Skills Level', labelAr: 'مستوى المهارات الشخصية', required: false, options: [{ value: '', labelEn: 'Select level', labelAr: 'اختر المستوى' }, { value: 'excellent', labelEn: 'Excellent', labelAr: 'ممتاز' }, { value: 'very-good', labelEn: 'Very Good', labelAr: 'جيد جداً' }, { value: 'good', labelEn: 'Good', labelAr: 'جيد' }, { value: 'acceptable', labelEn: 'Acceptable', labelAr: 'مقبول' }] },
                { name: 'institutionName', type: 'text', labelEn: 'Institution / University Name', labelAr: 'اسم المؤسسة أو الجامعة', required: false },
            ] },
            { titleEn: 'Academic Information', titleAr: 'المعلومات الأكاديمية', fields: [
                { name: 'targetFaculty', type: 'select', labelEn: 'Faculty to Apply To', labelAr: 'الكلية المراد التقدم لها', required: true, options: [{ value: '', labelEn: 'Select faculty', labelAr: 'اختر الكلية' }, { value: 'ai', labelEn: 'Faculty of Artificial Intelligence', labelAr: 'كلية الذكاء الاصطناعي' }, { value: 'business', labelEn: 'Faculty of Business Administration', labelAr: 'كلية إدارة الأعمال' }, { value: 'construction', labelEn: 'Faculty of Building Construction Engineering', labelAr: 'كلية هندسة التشييد والبناء' }, { value: 'dentistry', labelEn: 'Faculty of Dentistry', labelAr: 'كلية طب الأسنان' }, { value: 'medicine', labelEn: 'Faculty of Medicine', labelAr: 'كلية الطب' }, { value: 'petroleum', labelEn: 'Faculty of Petroleum Engineering', labelAr: 'كلية هندسة النفط' }, { value: 'pharmacy', labelEn: 'Faculty of Pharmacy', labelAr: 'كلية الصيدلة' }] },
                { name: 'generalSpecialization', type: 'text', labelEn: 'General Specialization', labelAr: 'التخصص العام', placeholderEn: 'e.g. Computer Science', placeholderAr: 'مثال: علوم الحاسوب', required: true },
                { name: 'preciseSpecialization', type: 'text', labelEn: 'Precise Specialization', labelAr: 'التخصص الدقيق', placeholderEn: 'e.g. Machine Learning', placeholderAr: 'مثال: تعلم الآلة', required: true },
                { name: 'academicRank', type: 'select', labelEn: 'Academic Rank', labelAr: 'الرتبة الأكاديمية', required: true, options: [{ value: '', labelEn: 'Select rank', labelAr: 'اختر الرتبة' }, { value: 'professor', labelEn: 'Professor', labelAr: 'أستاذ' }, { value: 'associate-professor', labelEn: 'Associate Professor', labelAr: 'أستاذ مشارك' }, { value: 'assistant-professor', labelEn: 'Assistant Professor', labelAr: 'أستاذ مساعد' }, { value: 'lecturer', labelEn: 'Lecturer', labelAr: 'محاضر' }, { value: 'teaching-assistant', labelEn: 'Teaching Assistant', labelAr: 'مساعد تدريس' }] },
                { name: 'contractType', type: 'select', labelEn: 'Contract Type', labelAr: 'نوع التعاقد', required: true, options: [{ value: '', labelEn: 'Select contract type', labelAr: 'اختر نوع التعاقد' }, { value: 'full-time', labelEn: 'Full-time', labelAr: 'دوام كامل' }, { value: 'part-time', labelEn: 'Part-time', labelAr: 'دوام جزئي' }, { value: 'visiting', labelEn: 'Visiting', labelAr: 'زائر' }, { value: 'contract', labelEn: 'Contract', labelAr: 'عقد' }] },
            ] },
            { titleEn: 'CV & Documents', titleAr: 'السيرة الذاتية والمستندات', fields: [
                { name: 'cvFile', type: 'file', labelEn: 'Upload CV (PDF)', labelAr: 'رفع السيرة الذاتية (PDF)', accept: '.pdf', required: true },
                { name: 'coverLetter', type: 'textarea', labelEn: 'Cover Letter / Statement of Purpose', labelAr: 'رسالة التغطية / بيانات الغرض', placeholderEn: 'Tell us about yourself and why you are applying...', placeholderAr: 'أخبرنا عن نفسك ولماذا تقدم...', required: false, rows: 6 },
            ] },
            { titleEn: 'Additional Requirements', titleAr: 'المتطلبات الإضافية', fields: [
                { name: 'hasPriorCriminalRecord', type: 'select', labelEn: 'Do you have any prior criminal record?', labelAr: 'هل لديك أي سجل جنائي سابق؟', required: true, options: [{ value: '', labelEn: 'Select', labelAr: 'اختر' }, { value: 'no', labelEn: 'No', labelAr: 'لا' }, { value: 'yes', labelEn: 'Yes', labelAr: 'نعم' }] },
                { name: 'canProvideReferences', type: 'select', labelEn: 'Can you provide professional references?', labelAr: 'هل تستطيع تقديم مراجع مهنية؟', required: true, options: [{ value: '', labelEn: 'Select', labelAr: 'اختر' }, { value: 'yes', labelEn: 'Yes', labelAr: 'نعم' }, { value: 'no', labelEn: 'No', labelAr: 'لا' }] },
                { name: 'agreeToTerms', type: 'checkbox', labelEn: 'I confirm that all information provided is accurate and I agree to the university\'s hiring policies.', labelAr: 'أقر بأن جميع المعلومات المقدمة صحيحة وأوافق على سياسات التوظيف في الجامعة.', required: true },
            ] },
        ],
    },
};

export function getFormSchema(formId) {
    return dynamicForms[formId] || null;
}
