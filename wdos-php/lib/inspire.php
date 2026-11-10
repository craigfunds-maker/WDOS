<?php
declare(strict_types=1);
/* Today's card for the signed-in leader. There is no menu entry or library page: a message appears on the
 * dashboard at its time and arrives as a notification. */
route('GET', '/inspiration/today', fn() => ['card' => insp_today(require_user())]);
