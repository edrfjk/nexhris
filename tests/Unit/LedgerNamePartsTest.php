<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * The three name cells on the official ledger card.
 *
 * The card prints FAMILY NAME, FIRST NAME and M.I. in separate boxes, so the
 * split has to be right on paper. Filipino surnames carrying a particle —
 * Sta. Ana, Dela Cruz, De Guzman — were losing the particle: the card read
 * FAMILY NAME "ANA" with "S." stranded in the middle-initial box.
 */
class LedgerNamePartsTest extends TestCase
{
    /**
     * @dataProvider names
     */
    public function test_a_name_splits_into_the_cells_the_card_prints(
        string $name,
        string $family,
        string $first,
        string $middle,
    ): void {
        $this->assertSame(
            ['family' => $family, 'first' => $first, 'middle' => $middle],
            (new User(['name' => $name]))->nameParts(),
            $name . ' is split into the wrong cells',
        );
    }

    public static function names(): array
    {
        return [
            'plain three-part name' => ['Mary Rose Niro', 'NIRO', 'MARY', 'R.'],
            'plain two-part name' => ['Jose Rizal', 'RIZAL', 'JOSE', ''],

            'Sta. particle' => ['Diana Sofia L. Sta. Ana', 'STA. ANA', 'DIANA SOFIA', 'L.'],
            'Dela particle' => ['Juan Dela Cruz', 'DELA CRUZ', 'JUAN', ''],
            'De particle' => ['Ana De Guzman', 'DE GUZMAN', 'ANA', ''],
            'Del particle' => ['Pedro Del Rosario', 'DEL ROSARIO', 'PEDRO', ''],
            'Delos particle' => ['Maria Delos Santos', 'DELOS SANTOS', 'MARIA', ''],

            // HR most often types the surname first, comma-separated.
            'surname first' => ['Sta. Maria, Jose R.', 'STA. MARIA', 'JOSE', 'R.'],
            'surname first, no initial' => ['Niro, Mary', 'NIRO', 'MARY', ''],

            // A mononym is all surname — it must not fill the first-name box.
            'single word' => ['Madonna', 'MADONNA', '', ''],
        ];
    }
}
