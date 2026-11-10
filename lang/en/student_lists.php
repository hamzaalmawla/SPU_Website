<?php

declare(strict_types=1);

return [
    'grades' => [
        'excellent' => 'Excellent',
        'very_good' => 'Very Good',
        'good' => 'Good',
        'pass' => 'Pass',
    ],

    'semesters' => [
        'first' => 'First Semester',
        'second' => 'Second Semester',
        'summer' => 'Summer Semester',
    ],

    'study_years' => [
        1 => 'First Year',
        2 => 'Second Year',
        3 => 'Third Year',
        4 => 'Fourth Year',
        5 => 'Fifth Year',
        6 => 'Sixth Year',
    ],

    'public' => [
        'grade' => 'Grade',
    ],

    'admin' => [
        'import_action' => 'Import from decision (.docx)',
        'import_alumni_heading' => 'Import graduates from a faculty council decision',
        'import_honor_heading' => 'Import top students from a faculty council decision',
        'import_description' => 'Upload the Word file as the faculty sent it. You will see a preview before anything is saved.',
        'file' => 'Decision file (.docx)',
        'file_help' => 'Word files only, up to 5 MB.',
        'read_file' => 'Read file',
        'confirm_heading' => 'Review the import',
        'confirm_action' => 'Import and publish',
        'academic_year' => 'Academic year',
        'academic_year_help' => 'As 2025/2026',
        'semester' => 'Semester',
        'departments' => 'Departments',
        'departments_help' => 'The department shown for each specialization the decision lists. Your choices are remembered for the next import.',
        'department_for' => ':text (:count students)',
        'no_department' => 'No department',
        'needs_choice' => 'Check the department',
        'summary' => 'Faculty: :faculty · File: :file · Decision: :number',
        'counts' => 'New: :create · Update: :update · Not imported: :skip',
        'history_action' => 'Import history',
        'history_heading' => 'Previous imports',
        'history_empty' => 'Nothing has been imported yet.',
        'undo' => 'Undo',
        'undo_confirm' => 'Students this import added will be removed and those it updated restored to their earlier values. Continue?',
        'undone' => 'Undone :date',
        'imported' => 'Imported: :created new, :updated updated, :skipped not imported.',
        'undo_done' => 'The import was undone.',
        'columns' => [
            'line' => '#',
            'id' => 'University ID',
            'name' => 'Name',
            'department' => 'Department',
            'gpa' => 'GPA',
            'grade' => 'Grade',
            'year' => 'Year',
            'rank' => 'Rank',
            'action' => 'Action',
            'imported_at' => 'Date',
            'imported_by' => 'By',
            'file' => 'File',
            'result' => 'Result',
        ],
        'actions' => [
            'create' => 'New',
            'update' => 'Update',
            'skip' => 'Not imported',
        ],
        'possible_duplicate' => 'May already be listed: :name',
    ],

    'issues' => [
        'missing_id' => 'No valid university ID',
        'missing_name' => 'No name',
        'bad_gpa' => 'Invalid GPA',
        'bad_grade' => 'Unknown grade',
        'missing_study_year' => 'Study year unclear',
        'duplicate_in_file' => 'Repeated in the file',
    ],

    'warnings' => [
        'missing_semester' => 'The decision does not state its academic year or semester. Choose them below.',
        'departments_need_choice' => 'Some specializations did not match a department confidently. Check the marked departments.',
        'possible_duplicates' => 'Some names look like alumni from the old site that have no university ID. They will be added as new records.',
    ],

    'errors' => [
        'not_docx' => 'The file could not be read. Upload a Word .docx file.',
        'no_rows' => 'No student table was found in the file.',
        'wrong_kind' => 'This file does not look like the list this page expects (graduates or top students).',
        'upload_missing' => 'The uploaded file has expired. Upload it again.',
        'forbidden' => 'You do not have permission to publish on this page.',
        'unknown_faculty' => 'Unknown faculty.',
        'unknown_kind' => 'Unknown list type.',
        'bad_academic_year' => 'The academic year must look like 2025/2026.',
        'bad_semester' => 'Choose the semester.',
        'bad_department' => 'The chosen department does not belong to this faculty.',
        'import_not_found' => 'This import was not found or has already been undone.',
        'undo_newer_first' => 'Undo the newer import first.',
    ],
];
