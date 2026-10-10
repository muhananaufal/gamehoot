<?php

declare(strict_types=1);

use App\Support\QueryErrorLog;

describe('K5 masking of database error messages (audit SEC-9)', function (): void {
    it('hides the clashing value but keeps the key that refused it', function (): void {
        $masked = QueryErrorLog::maskValues("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '01a1-jose a' for key 'people.people_event_id_name_normalized_unique'");

        expect($masked)->toBe("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '?' for key 'people.people_event_id_name_normalized_unique'");
    });

    it('keeps column and table names and hides string values', function (): void {
        expect(QueryErrorLog::maskValues("Incorrect string value: '\\xF0\\x9F' for column 'name' at row 1"))
            ->toBe("Incorrect string value: '?' for column 'name' at row 1")
            ->and(QueryErrorLog::maskValues("Table 'pentahoot.people' doesn't exist"))
            ->toBe("Table 'pentahoot.people' doesn't exist");
    });

    it('does not leak the rest of a value that contains an escaped quote', function (): void {
        expect(QueryErrorLog::maskValues("Duplicate entry 'O\\'Neil secret' for key 'k'"))
            ->toBe("Duplicate entry '?' for key 'k'");
    });
});
