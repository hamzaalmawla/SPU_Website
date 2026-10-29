import assert from 'node:assert/strict';
import { test } from 'node:test';

import { masterProgramRelations } from '../../resources/js/alpine/masterProgram.js';

test('a course is related to its prerequisites and to the courses it opens', () => {
    const related = masterProgramRelations([
        { code: 'MADS101', prerequisites: [] },
        { code: 'MADS103', prerequisites: [] },
        { code: 'MADS202', prerequisites: ['MADS101', 'MADS103'] },
        { code: 'MADS303', prerequisites: ['MADS202', 'MADS999'] },
    ]);

    assert.deepEqual([...related.get('MADS202')].sort(), ['MADS101', 'MADS103', 'MADS303']);
    assert.deepEqual([...related.get('MADS101')], ['MADS202']);
    assert.deepEqual([...related.get('MADS303')], ['MADS202']);
    assert.equal(related.has('MADS999'), false);
});
