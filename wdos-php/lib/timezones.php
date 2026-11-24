<?php
declare(strict_types=1);
/* v109.53: meeting times in each person's own time.
 * Times are kept in UTC. A person's time zone is the one her phone reported (users.tz); if WDOS has not heard from her phone
 * yet, it is her country's main time zone; if her country is not known either, it is Nigeria's (WODDI HQ). */

/** Countries that span several time zones: the zone of the capital or the largest city. Everywhere else PHP's own country list decides. */
const TZ_MAIN = ['US' => 'America/New_York', 'CA' => 'America/Toronto', 'BR' => 'America/Sao_Paulo', 'AU' => 'Australia/Sydney', 'RU' => 'Europe/Moscow',
  'MX' => 'America/Mexico_City', 'CD' => 'Africa/Kinshasa', 'ID' => 'Asia/Jakarta', 'KZ' => 'Asia/Almaty', 'ES' => 'Europe/Madrid', 'PT' => 'Europe/Lisbon',
  'CL' => 'America/Santiago', 'AR' => 'America/Argentina/Buenos_Aires', 'EC' => 'America/Guayaquil', 'NZ' => 'Pacific/Auckland', 'MN' => 'Asia/Ulaanbaatar',
  'UA' => 'Europe/Kyiv', 'DE' => 'Europe/Berlin', 'GB' => 'Europe/London', 'CN' => 'Asia/Shanghai', 'MY' => 'Asia/Kuala_Lumpur', 'GL' => 'America/Nuuk'];
const TZ_HQ = 'Africa/Lagos';

function tz_valid(string $tz): bool { static $all = null; $all ??= array_flip(DateTimeZone::listIdentifiers()); return $tz !== '' && isset($all[$tz]); }
function tz_country(?string $cc): string {
  $cc = strtoupper((string) $cc); if (strlen($cc) !== 2) return TZ_HQ;
  if (isset(TZ_MAIN[$cc]) && tz_valid(TZ_MAIN[$cc])) return TZ_MAIN[$cc];
  try { $l = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $cc); } catch (Throwable $e) { $l = []; }
  return $l[0] ?? TZ_HQ;
}
/** The time zone to write a time in for this person (a users row, or any array with tz and country_code). */
function tz_of(array $u): string { $t = (string) ($u['tz'] ?? ''); return tz_valid($t) ? $t : tz_country($u['country_code'] ?? ''); }
/** "Thu 27 Nov 2026, 11:00 EAT (UTC+3)": the time where she is, with the zone written out so nobody has to guess. */
function tz_when(int $ts, string $tz, bool $date = true, bool $long = false): string {
  $d = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(tz_valid($tz) ? $tz : TZ_HQ));
  $abbr = $d->format('T'); $off = $d->format('P'); $off = $off === '+00:00' ? 'UTC' : 'UTC' . preg_replace('/:00$/', '', preg_replace('/^([+-])0/', '$1', $off));
  $zone = preg_match('/^[A-Z]{2,5}$/', $abbr) ? "$abbr ($off)" : $off;
  return ($date ? $d->format($long ? 'l j F Y, ' : 'D j M Y, ') : '') . $d->format('H:i') . ' ' . $zone;
}
/** A person's time zone by id (cached for one request). */
function tz_for_id(int $id): string { static $c = []; if (!isset($c[$id])) { $r = row('SELECT tz, country_code FROM users WHERE id=?', [$id]); $c[$id] = $r ? tz_of($r) : TZ_HQ; } return $c[$id]; }
/** The people a where-clause reaches, sorted by the time zone each one should read times in: [tz => [ids]]. */
function tz_groups(string $whereSql, array $params): array {
  $g = [];
  foreach (rows("SELECT u.id, u.tz, u.country_code FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id WHERE u.status='active' AND ($whereSql)", $params) as $r) $g[tz_of($r)][] = (int) $r['id'];
  return $g;
}
/** notify_many, with a body written for each time zone: $body is a function of the time zone. One call per zone actually present. */
function notify_many_timed(string $whereSql, array $params, string $kind, string $title, callable $body, string $url): int {
  $n = 0;
  foreach (tz_groups($whereSql, $params) as $tz => $ids)
    foreach (array_chunk($ids, 500) as $part)
      $n += notify_many('u.id IN (' . implode(',', array_fill(0, count($part), '?')) . ')', $part, $kind, $title, $body($tz), $url);
  return $n;
}

/* The phone says which time zone it is in; WDOS keeps it so emails and notifications show times in her own time.
 * Declared from api/index.php once the request path is known (this file is loaded before that). */
function tz_routes(): void {
route('POST', '/me/tz', function () {
  $u = require_user(); $tz = str_in('tz', 60);
  if (!tz_valid($tz)) fail(422, 'That time zone is not known.', 'tz');
  if (($u['tz'] ?? '') !== $tz) { try { q('UPDATE users SET tz=? WHERE id=?', [$tz, (int) $u['id']]); } catch (PDOException $e) { /* before migration 052 */ } }
  return ['ok' => true, 'tz' => $tz];
});
}
