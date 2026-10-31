# Conversion report

- Program ids kept from the old plan: hr = Smart Human Resource Management, accounting = Smart Accounting, marketing = Digital Marketing, finance = FinTech, mis = Business Intelligence and Logistics.
- The old Business Administration program (management) has no successor and is not in the new plan. The Business departments page links its "management" department to it, and staff may have specialization_id = management: an editor must relink them or make the department a section.
- Prerequisite codes from the old numbering in the source: REQU102 (BFCR.2.08) read as UR-2-02 Computer Skills; ASCR.1.03 (BFCR.4.14) read as BFCR.1.03 Mathematics for Business; REQU204 (BFCR.3.18) read as UR-2-04 English Language (2).
- English Language (1) and (2) are 3 credit hours as in the university requirements table; the level lists say 2.
- The level 3 list has a university elective of 3 credit hours; university electives are 2 credit hours each (4 in total). Their options are listed in level 3, and English Language (3), which follows English (2), in level 5 with the second university elective slot.
- Faculty electives (6 credit hours, two courses) are listed in level 3, except English for Business and Accounting for Partnerships, whose prerequisites are in level 3, listed in level 4. The source gives them credits only: hours are 3 theory.
- Specialization courses: the source gives no English titles (translated here), no theory/practical split (hours are the credits, theory only) and no prerequisites (none set). Check each on the study plan board.
- Department electives: the source names a 3 credit hour department elective in levels 5 and 6 but lists no courses, so each is a placeholder course "Department elective" without a code.
- University and faculty courses keep the source's Arabic descriptions; English descriptions are translations. Specialization courses have no descriptions.
- Import manually after review: php artisan faculty:import-study-plan business-administration database/seeders/Data/study_plans/business-administration.json --user=<id> --replace-edited --write (saves an unpublished draft; publish it from the board).
