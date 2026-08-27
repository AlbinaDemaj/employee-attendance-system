<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Renditje sipas një liste vlerash të caktuara, e shkruar në SQL standard.
 *
 * MySQL-i e ofron këtë me `FIELD(kolona, 'a', 'b', 'c')`, por ai funksion nuk
 * ekziston në PostgreSQL. Kjo klasë prodhon një shprehje `CASE` që e ruan
 * saktësisht të njëjtën sjellje: vlera e parë merr 1, e dyta 2 e kështu me
 * radhë, ndërsa çdo vlerë që nuk gjendet në listë (ose NULL) merr 0 — pra,
 * ashtu si te `FIELD`, vlerat e panjohura dalin të parat kur renditet ASC.
 */
class SqlOrder
{
    /**
     * @param  list<string>  $values
     */
    public static function byValues(string $column, array $values): string
    {
        if ($values === []) {
            throw new InvalidArgumentException('Lista e vlerave nuk mund të jetë bosh.');
        }

        // Kolonat dhe vlerat vijnë gjithmonë nga katalogët tanë, kurrë nga
        // përdoruesi, por i filtrojmë prapëseprapë që kjo shprehje të mos
        // bëhet kurrë rrugë për SQL injection.
        $column = self::identifier($column);

        $cases = '';
        foreach (array_values($values) as $index => $value) {
            $cases .= sprintf(" WHEN %s = '%s' THEN %d", $column, self::literal($value), $index + 1);
        }

        return sprintf('CASE%s ELSE 0 END', $cases);
    }

    private static function identifier(string $column): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException("Emër kolone i papranueshëm: {$column}");
        }

        return $column;
    }

    private static function literal(string $value): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException("Vlerë e papranueshme për renditje: {$value}");
        }

        return $value;
    }
}
