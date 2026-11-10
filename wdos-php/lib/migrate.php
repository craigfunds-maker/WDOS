<?php
declare(strict_types=1);
/* Database migrations. They run by themselves the first time the app is opened
 * after an update, so nobody has to press a button. The setup page can still run them by hand. */

function run_migrations(): array {
  db()->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) NOT NULL PRIMARY KEY, ran_at DATETIME NOT NULL) DEFAULT CHARSET=utf8mb4');
  $done = array_column(rows('SELECT name FROM migrations'), 'name'); $ran = [];
  $files = glob(__DIR__ . '/../migrations/*.sql'); sort($files);
  foreach ($files as $f) {
    $name = basename($f);
    if (in_array($name, $done, true)) continue;
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($f));
    foreach (array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql))) as $stmt) {
      try { db()->exec($stmt); }
      catch (PDOException $e) {
        // a file that stopped halfway can be run again: skip what is already there (table, column, index)
        if (!in_array((int) ($e->errorInfo[1] ?? 0), [1050, 1060, 1061], true)) throw $e;
      }
    }   // one statement at a time (TiDB rule)
    q('INSERT INTO migrations (name,ran_at) VALUES (?,?)', [$name, now()]);
    $ran[] = $name;
  }
  return $ran;
}

/** Called once per app load. One cheap count; runs the new files only when there are any. */
function ensure_migrated(): void {
  $files = count(glob(__DIR__ . '/../migrations/*.sql'));
  try { $done = (int) val('SELECT COUNT(*) FROM migrations'); } catch (PDOException $e) { $done = 0; }
  if ($done >= $files) return;
  // one request at a time: the primary key on app_settings is the lock
  try { db()->exec('CREATE TABLE IF NOT EXISTS app_settings (k VARCHAR(60) NOT NULL PRIMARY KEY, v TEXT NOT NULL) DEFAULT CHARSET=utf8mb4'); q("INSERT INTO app_settings (k,v) VALUES ('migrating',?)", [now()]); }
  catch (PDOException $e) {
    $since = val("SELECT v FROM app_settings WHERE k='migrating'");
    if ($since && strtotime($since . ' UTC') > time() - 180) return;      // another request is doing it
    q("UPDATE app_settings SET v=? WHERE k='migrating'", [now()]);          // a stale lock from a crashed run
  }
  try { run_migrations(); } finally { q("DELETE FROM app_settings WHERE k='migrating'"); }
}
