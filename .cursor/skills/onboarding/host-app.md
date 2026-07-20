# Host app deep dive — stock osTicket

Confirm class names, paths, and version against the live tree — this is a
living app and these lists are illustrative, not exhaustive.

## Identity

- Declared version: `1.18-git` (`MAJOR_VERSION` / `THIS_VERSION` in `bootstrap.php`).
  `WHATSNEW.md` changelog tops out at v1.18.4.
- Fork: `origin` → `jazneb23/osTicket`; `upstream` → `osTicket/osTicket`.
- License: GPL2 (`LICENSE.txt`).
- Runtime: PHP 8.2–8.4 (8.4 recommended), `mysqli` extension, MySQL ≥ 5.5 (per `README.md`).

## Real code patterns (read these first)

This is the part that actually teaches you to read/write osTicket code. Every
snippet below is quoted verbatim from the live tree with `file:line` — open
the file and look around it, don't just trust the excerpt (line numbers
drift as the code changes; re-check before relying on them).

**Reading the codebase** (1–10): ORM models, QuerySets, a full controller
trace, globals, security helpers, signals, the reply write-path, config
access, permission checks, the ORM/legacy-SQL gotcha.

**Deeper subsystems** (11–17): error handling/logging, seeing PHP errors in
dev, dynamic forms, file/attachment handling, the email pipeline, cron,
validation.

**Workflow recipes** (18–20): add a staff admin page, add a DB migration,
work with custom lists.

### 1. ORM model definition (`static $meta`)

Models extend `VerySimpleModel` and declare their table, primary key, eager
loads, and FK joins in a `$meta` block:

```42:57:include/class.ticket.php
    static $meta = array(
        'table' => TICKET_TABLE,
        'pk' => array('ticket_id'),
        'select_related' => array('topic', 'staff', 'user', 'team', 'dept',
            'sla', 'thread', 'child_thread', 'user__default_email', 'status'),
        'joins' => array(
            'user' => array(
                'constraint' => array('user_id' => 'User.id'),
                'null' => true,
            ),
            'status' => array(
                'constraint' => array('status_id' => 'TicketStatus.id')
            ),
            'lock' => array(
                'constraint' => array('lock_id' => 'Lock.lock_id'),
                'null' => true,
            ),
```

`constraint` declares a forward FK join; `reverse` (elsewhere in the same
block, e.g. `'thread' => array('reverse' => 'TicketThread.ticket', ...)`)
declares the inverse side. `Staff` (`include/class.staff.php:28`) is a
simpler example of the same pattern if `Ticket`'s is too dense to start with.

### 2. QuerySet usage in the wild

```31:33:pages/index.php
$pages = Page::objects()->filter(array(
    'name__like' => "$first_word%"
));
```

```37:45:include/ajax.thread.php
        $hits = Ticket::objects()
            ->filter(Q::any(array(
                'number__startswith' => $_REQUEST['q'],
            )))
            ->filter($visibility)
            ->values('number', 'user__emails__address')
            ->annotate(array('tickets' => SqlAggregate::COUNT('ticket_id')))
            ->order_by('-created')
            ->limit($limit);
```

```360:367:include/class.email.php
   static function getIdByEmail($email) {
        $qs = static::objects()->filter(Q::any(array(
                        'email'  => $email,
                        )))
            ->values_flat('email_id');

        $row = $qs->first();
        return $row ? $row[0] : false;
    }
```

`__like` / `__startswith` are Django-style field lookups; `Q::any`/`Q::all`
compose OR/AND groups; `->values()`/`->values_flat()` project columns;
`->annotate()` adds aggregates. `->one()` and `->all()` are also common
(e.g. `TicketForm::objects()->one()` in `tickets.php`).

### 3. A full staff controller, end to end: `scp/tickets.php`

Bootstrap pulls in staff auth/globals, then the domain classes it needs:

```17:24:scp/tickets.php
require('staff.inc.php');
require_once(INCLUDE_DIR.'class.ticket.php');
require_once(INCLUDE_DIR.'class.dept.php');
require_once(INCLUDE_DIR.'class.filter.php');
require_once(INCLUDE_DIR.'class.canned.php');
require_once(INCLUDE_DIR.'class.json.php');
require_once(INCLUDE_DIR.'class.dynamic_forms.php');
require_once(INCLUDE_DIR.'class.export.php');       // For paper sizes
```

Lookup + ACL check on the requested ticket:

```35:43:scp/tickets.php
if(isset($_REQUEST['id']) || isset($_REQUEST['number'])) {
    if($_REQUEST['id'] && !($ticket=Ticket::lookup($_REQUEST['id'])))
         $errors['err']=sprintf(__('%s: Unknown or invalid ID.'), __('ticket'));
    elseif($_REQUEST['number'] && !($ticket=Ticket::lookup(array('number' => $_REQUEST['number']))))
         $errors['err']=sprintf(__('%s: Unknown or invalid number.'), __('ticket'));
     elseif(!$ticket || !$ticket->checkStaffPerm($thisstaff)) {
         $errors['err']=__('Access denied. Contact admin if you believe this is in error');
         $ticket=null; //Clear ticket obj.
     }
}
```

POST actions dispatch inline (e.g. `case 'reply': $ticket->postReply(...)`
around line 220), then the page picks which template to hand off to and
wraps it in the shared header/footer:

```515:571:scp/tickets.php
if($ticket) {
    $ost->setPageTitle(sprintf(__('Ticket #%s'),$ticket->getNumber()));
    $nav->setActiveSubMenu(-1);
    $inc = 'ticket-view.inc.php';
    if ($_REQUEST['a']=='edit'
            && $ticket->checkStaffPerm($thisstaff, Ticket::PERM_EDIT)) {
        $inc = 'ticket-edit.inc.php';
        // ...
    }
} else {
    $inc = 'templates/queue-tickets.tmpl.php';
    if ((isset($_REQUEST['a']) && $_REQUEST['a']=='open') &&
            $thisstaff->hasPerm(Ticket::PERM_CREATE, false)) {
        $inc = 'ticket-open.inc.php';
    }
}

require_once(STAFFINC_DIR.'header.inc.php');
require_once(STAFFINC_DIR.$inc);
print $response_form->getMedia();
require_once(STAFFINC_DIR.'footer.inc.php');
```

This "require the right `.inc.php`, wrapped in header/footer" shape is the
same across nearly every `scp/*.php` and client page — once you've read one,
you can read them all.

### 4. Where the four omnipresent globals come from

| Global | Set at | Type |
|--------|--------|------|
| `$ost` | `main.inc.php:34` via `osTicket::start()` | `osTicket` (`include/class.osticket.php`) |
| `$cfg` | `main.inc.php:34` via `$ost->getConfig()` | `OsticketConfig` |
| `$thisstaff` | `scp/staff.inc.php:64` | `Staff` (`include/class.staff.php`) |
| `$thisclient` | `client.inc.php:52` | `ClientSession` wrapping `EndUser` |

```34:35:main.inc.php
if(!($ost=osTicket::start()) || !($cfg = $ost->getConfig()))
Bootstrap::croak(__('Unable to load config info from DB.').' '.__('Get technical help!'));
```

Almost every function signature in `include/class.ticket.php` etc. starts
with `global $thisstaff, $cfg;` rather than passing them as parameters —
expect that pattern everywhere, it's not local to one file.

### 5. Security helpers — real call sites

**CSRF**, enforced once for all staff mutating requests in
`scp/staff.inc.php:106`:

```106:111:scp/staff.inc.php
/******* CSRF Protectin *************/
// Enforce CSRF protection for state-changing methods
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])
        && !$ost->checkCSRFToken()) {
    Http::response(400, __('Valid CSRF Token Required'));
```

**Output escaping** — `Format::htmlchars(...)` wraps any user-controlled
string before it hits a template, e.g. `include/staff/templates/thread-entry.tmpl.php:74-85`.

**Legacy string-SQL escaping** — `db_input()` (`include/mysqli.php:322`)
escapes/quotes a value for hand-built SQL; still used where the ORM hasn't
replaced older code, e.g.:

```230:234:include/class.sla.php
        $sql='DELETE FROM '.SLA_TABLE.' WHERE id='.db_input($id).' LIMIT 1';
        if(db_query($sql) && ($num=db_affected_rows())) {
            db_query('UPDATE '.DEPT_TABLE.' SET sla_id=0 WHERE sla_id='.db_input($id));
```

### 6. Signals (the plugin/extensibility hook)

Connect once, anywhere, at bootstrap-adjacent time — here the search indexer
subscribes to model lifecycle events (`include/class.search.php:220`):

```220:227:include/class.search.php
        Signal::connect('threadentry.created', array($this, 'createModel'));
        Signal::connect('ticket.created', array($this, 'createModel'));
        Signal::connect('user.created', array($this, 'createModel'));
        Signal::connect('organization.created', array($this, 'createModel'));
        Signal::connect('model.created', array($this, 'createModel'), 'FAQ');
```

Send from wherever the real event happens — a `ThreadEntry` firing after
save (`include/class.thread.php:1811`):

```1811:1823:include/class.thread.php
        if (!$entry->save(true))
            return false;
        // ...
        Signal::send('threadentry.created', $entry);

        return $entry;
```

This is the extension point plugins use instead of monkey-patching core
classes — grep `Signal::send` before assuming you need to edit a core file
to react to an event.

### 7. Staff reply → `ThreadEntry` (a full write path)

`scp/tickets.php` calls `$ticket->postReply($vars, $errors, $alert)`, which
delegates to the thread and then fans out email/signal side effects:

```3317:3323:include/class.ticket.php
    function postReply($vars, &$errors, $alert=true, $claim=true) {
        global $thisstaff, $cfg;
        // ...
        if (!($response = $this->getThread()->addResponse($vars, $errors)))
            return null;
```

```3189:3202:include/class.thread.php
    function addResponse($vars, &$errors) {
        $vars['threadId'] = $this->getId();
        $vars['userId'] = 0;
        if ($message = $this->getLastMessage())
            $vars['pid'] = $message->getId();
        if (!($resp = ResponseThreadEntry::add($vars, $errors)))
            return $resp;
        $this->lastresponse = SqlFunction::NOW();
        $this->save(true);
        return $resp;
    }
```

`ResponseThreadEntry::add()` → `save()` → `Signal::send('threadentry.created', ...)`
(pattern 6, above). Back in `postReply`, after the save: optional auto-claim,
a status change, `onResponse()` housekeeping, then a templated email to
recipients if `$alert` is true. This create → save → signal → notify shape
repeats for notes and messages too — same `Thread` class, different
`*ThreadEntry` subclass.

### 8. Reading a system setting

Getters on `Config` are thin wrappers over `$this->get('key')`:

```603:605:include/class.config.php
    function getDefaultDeptId() {
        return $this->get('default_dept_id');
    }
```

Call sites read through `$cfg`, e.g. `include/class.ticket.php` ticket-create
fallback: `$deptId = $cfg->getDefaultDeptId();`. Prefer adding a getter over
calling `$cfg->get('raw_key')` directly from feature code.

### 9. Permission checks

Role-scoped action gate inline in a controller (`scp/tickets.php:169`):

```169:171:scp/tickets.php
        case 'reply':
            if (!$role || !$role->hasPerm(Ticket::PERM_REPLY)) {
                $errors['err'] = __('Action denied. Contact admin for access');
```

Staff-level capability check (`scp/tickets.php:500`):
`if ($thisstaff->hasPerm(Ticket::PERM_CREATE, false)) { ... }`. The session
gate itself (`!$thisstaff || !$thisstaff->isValid()` → login redirect) is
earlier in `scp/staff.inc.php:71-81`, before any page code runs.

### 10. Gotcha: ORM and legacy string-SQL coexist in the same class

Don't assume a class is "pure ORM" or "pure legacy" — check each method.
`Sla::delete()` still hand-builds SQL (with an explicit TODO) while
`Dept`'s membership queries next door use the ORM:

```228:234:include/class.sla.php
        //TODO: Use ORM to delete & update
        $id=$this->getId();
        $sql='DELETE FROM '.SLA_TABLE.' WHERE id='.db_input($id).' LIMIT 1';
        if(db_query($sql) && ($num=db_affected_rows())) {
            db_query('UPDATE '.DEPT_TABLE.' SET sla_id=0 WHERE sla_id='.db_input($id));
```

Related gotcha: timezone math usually goes through `Misc::dbtime()`
(`include/class.misc.php`) rather than raw `time()`, because the DB and PHP
timezones can differ (`$cfg->getDbTimezone()`) — using `time()` directly in
a date comparison against a DB column is a common source of off-by-some-hours
bugs.

### 11. Error handling and logging

`BaseError` (and subclasses like `OSTFileError`) logs through `$ost->logError()`
on construction:

```20:36:include/class.error.php
class BaseError extends Exception {
    static $title = '';
    static $sendAlert = true;

    function __construct($message) {
        global $ost;

        parent::__construct(__($message));

        if ($ost) {
            $message = str_replace(ROOT_DIR, '(root)/', _S($message));

            if ($ost->getConfig()->getLogLevel() == 3)
                $message .= "\n\n" . $this->getBacktrace();

            $ost->logError($this->getTitle(), $message, static::$sendAlert);
        }
    }
```

`osTicket` exposes `logDebug()` / `logInfo()` / `logWarning()` / `logError()`
(`include/class.osticket.php:250-264`), all writing to `SYSLOG_TABLE` (visible
under Admin → System Logs), gated by the configured log level. A real call
site: `include/class.auth.php` logs excessive/failed login attempts via
`$ost->logWarning(...)`. Separately, `Bootstrap::croak()`
(`bootstrap.php:340`) is the last-resort path — it emails the admin and
returns HTTP 500 when bootstrap itself can't proceed (e.g. no DB config).

### 12. Seeing PHP errors during development

There is **no** `DEBUG` constant in `include/ost-config.php`. PHP-level error
display is controlled in `bootstrap.php` — note the comment and the code
actually disagree:

```23:31:bootstrap.php
        #Error reporting...Good idea to ENABLE error reporting to a file. i.e display_errors should be set to false
        $error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING;
        if (defined('E_DEPRECATED')) # 5.3.0
            $error_reporting &= ~(E_DEPRECATED | E_USER_DEPRECATED);
        error_reporting($error_reporting); //Respect whatever is set in php.ini (sysadmin knows better??)

        #Don't display errors
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
```

(`scp/ajax.php` explicitly flips `display_errors` back to `0` for AJAX
responses.) For **application-level** messages (not raw PHP errors), set
Admin → Settings → System → Default Log Level to `DEBUG` and read System
Logs — that's the closest thing to a debug flag this app has.

### 13. Dynamic forms (custom fields)

Forms and their fields are DB-backed models; field *types* are a static
registry mapping a type key to a widget/field class:

```29:41:include/class.dynamic_forms.php
class DynamicForm extends VerySimpleModel {

    static $meta = array(
        'table' => FORM_SEC_TABLE,
        'ordering' => array('title'),
        'pk' => array('id'),
        'joins' => array(
            'fields' => array(
                'reverse' => 'DynamicFormField.form',
            ),
        ),
    );
```

```605:616:include/class.forms.php
    static $types = array(
        /* @trans */ 'Basic Fields' => array(
            'text'  => array(   /* @trans */ 'Short Answer', 'TextboxField'),
            'memo' => array(    /* @trans */ 'Long Answer', 'TextareaField'),
            'thread' => array(  /* @trans */ 'Thread Entry', 'ThreadEntryField', false),
            'datetime' => array(/* @trans */ 'Date and Time', 'DatetimeField'),
            'bool' => array(    /* @trans */ 'Checkbox', 'BooleanField'),
            'choices' => array( /* @trans */ 'Choices', 'ChoiceField'),
            'files' => array(   /* @trans */ 'File Upload', 'FileUploadField'),
        ),
    );
```

Each field type is a class (e.g. `TextboxField` at `include/class.forms.php:1464`)
whose `render()` delegates to a widget (`FormField::render()`,
`class.forms.php:1252`). Custom lists (pattern 20 below) plug into this
registry too — that's how "Custom Lists" become selectable field types.

### 14. File and attachment handling

Upload lands on `AttachmentFile::upload()`:

```352:368:include/class.file.php
    static function upload($file, $ft='T', $deduplicate=true) {

        if(!$file['name'] || $file['error'] || !is_uploaded_file($file['tmp_name']))
            return false;

        list($key, $sig) = self::_getKeyAndHash($file['tmp_name'], true);

        $info=array('type'=>$file['type'],
                    'filetype'=>$ft,
                    'size'=>$file['size'],
                    'name'=>$file['name'],
                    'key'=>$key,
                    'signature'=>$sig,
                    'tmp_name'=>$file['tmp_name'],
                    );

        return static::create($info, $ft, $deduplicate);
    }
```

`GenericAttachments::upload()` (`include/class.attachment.php:134`) then
links a file (by id, array, or raw upload) to an owning object; a thread
entry wires its attachments right after `save()`:
`$entry->createAttachments($attached_files);` (`include/class.thread.php:1815`,
implementation at `:1265`) — the same create → save → attach → signal shape
as pattern 7.

### 15. Email fetch/parse → ticket or reply (4-hop chain)

1. **Cron kicks the fetcher** (`include/class.cron.php:23`):
   `osTicket\Mail\Fetcher::run();`
2. **Fetcher hands the raw message to the API** (`include/class.mailfetch.php:78`):
   `$this->getTicketsApi()->processEmail($this->mbox->getRawEmail($i), $defaults);`
3. **API matches an existing thread or creates a ticket** (`include/api.tickets.php:181-217`):
   tries `ThreadEntry::lookupByEmailHeaders($data, $seen)` first (existing
   conversation), falls back to `$this->createTicket($data, 'Email')`.
4. **MIME parsing** happens underneath via `include/class.mailparse.php:784`
   (`Mail_Parse` → `getHeaderInfo()`).

### 16. Cron

`Cron::run()` is the non-autocron entry point and lists every scheduled task
explicitly — there's no hidden scheduler config to go find:

```105:123:include/class.cron.php
    static function run(){ //called by outside cron NOT autocron
        global $ost;
        if (!$ost || $ost->isUpgradePending())
            return;

        self::MailFetcher();
        self::TicketMonitor();
        self::PurgeLogs();
        self::CleanExpiredSessions();
        self::CleanPwResets();
        // Run file purging about every 10 cron runs
        if (mt_rand(1, 9) == 4)
            self::CleanOrphanedFiles();
        self::PurgeDrafts();
        self::MaybeOptimizeTables();

        $data = array('autocron'=>false);
        Signal::send('cron', null, $data);
    }
```

`TicketMonitor()` is what actually calls `Ticket::checkOverdue()` (the same
method the demo's MOD-27 seam extracts). Invocation: CLI via `api/cron.php`
(checks `osTicket::is_cli()`), or remotely via `POST /api/tasks/cron`
(`setup/doc/api/tasks.md`), or opportunistically via a hidden image tag in
`include/staff/footer.inc.php` hitting `scp/autocron.php` on every staff
page load. There's no classic crontab line committed in this repo.

### 17. Validator

Field rules are plain arrays (`type`, `required`, `error`, `min`/`max`,
etc.) run through `Validator::process()`:

```3657:3667:include/class.ticket.php
        $fields = array();
        $fields['topicId']  = array('type'=>'int',      'required'=>1, 'error'=>__('Help topic selection is required'));
        $fields['slaId']    = array('type'=>'int',      'required'=>0, 'error'=>__('Select a valid SLA'));
        $fields['duedate']  = array('type'=>'date',     'required'=>0, 'error'=>__('Invalid date format - must be MM/DD/YY'));
        $fields['user_id']  = array('type'=>'int',      'required'=>0, 'error'=>__('Invalid user-id'));

        if (!Validator::process($fields, $vars, $errors) && !$errors['err'])
            $errors['err'] = sprintf('%s — %s',
                __('Missing or invalid data'),
                __('Correct any errors below and try again'));
```

`Validator::is_email()` (`include/class.validator.php:166`) is the go-to
static helper for email-address validation elsewhere in the codebase.

### 18. Common workflow — add a new staff admin page

There is no `menu.inc.php`; navigation is hardcoded in `include/class.nav.php`:

```270:274:include/class.nav.php
                case 'staff':
                    $subnav[]=array('desc'=>__('Agents'),'href'=>'staff.php','iconclass'=>'users');
                    $subnav[]=array('desc'=>__('Teams'),'href'=>'teams.php','iconclass'=>'teams');
                    $subnav[]=array('desc'=>__('Roles'),'href'=>'roles.php','iconclass'=>'lists');
                    $subnav[]=array('desc'=>__('Departments'),'href'=>'departments.php','iconclass'=>'departments');
```

A minimal admin controller (`scp/departments.php`) follows the same shape as
every other `scp/*.php`: `require('admin.inc.php')`, look up the object from
`$_REQUEST['id']`, a `switch (strtolower($_POST['do']))` for mutations, then
set nav state and hand off to a template:

```222:232:scp/departments.php
$page='departments.inc.php';
// ...
$nav->setTabActive('staff');
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.$page);
include(STAFFINC_DIR.'footer.inc.php');
```

**To add a new admin page**: create `scp/<name>.php` following this shape,
create `include/staff/<name>.inc.php` for its template, and add one
`$subnav[]` entry in the matching `case` inside `class.nav.php`.

### 19. Common workflow — add a DB migration

Patches live in `include/upgrader/streams/core/`, named `{from8}-{to8}.patch.sql`
(first 8 hex chars of the previous signature, then the new one), with an
optional same-named `.task.php` for PHP-driven data migration and an
optional `.cleanup.sql`. The upgrader globs `{from8}-*.patch.sql`
(`include/class.migrater.php:49`) and loads a matching `.task.php` by class
name when present (`include/class.upgrader.php:385`).

```1:9:include/upgrader/streams/core/e7038ce9-ddbe2e76.patch.sql
/**
 * @signature ddbe2e76ec38a2e58bdbff9109c07930
 * @version v1.15
 * @title Add indexes accumulated over time
 *
 * This patch adds all indexes we've implemented over time and adds the
 * `version` column to the plugin table (if not exists)
 */
```

A paired task file (e.g. `dad45ca2-61c9d5d7.task.php`) is a plain
`MigrationTask` subclass with a `run($max_time)` method, returning its own
class name as a string. After adding a patch, update
`include/upgrader/streams/core.sig` to the new tip signature — that's what
`osTicket::isUpgradePending()` compares against the DB's `schema_signature`.

### 20. Custom lists

`DynamicList` is itself an ORM model, and registers as a selectable dynamic-
form field type:

```147:159:include/class.list.php
class DynamicList extends VerySimpleModel implements CustomList {

    static $meta = array(
        'table' => LIST_TABLE,
        'ordering' => array('name'),
        'pk' => array('id'),
        'joins' => array(
            'items' => array(
                'reverse' => 'DynamicListItem.list',
            ),
        ),
    );
```

```613:613:include/class.list.php
FormField::addFieldTypes(/* @trans */ 'Custom Lists', array('DynamicList', 'getSelections'));
```

Specialized built-in lists (e.g. ticket statuses) subclass the same pattern
and register via `CustomListHandler::register('ticket-status', 'TicketStatusList')`
— useful if you ever need a first-class dropdown that isn't a raw
admin-managed list. Admin UI: **Manage → Lists**.

## Top-level surfaces

| Path | Role |
|------|------|
| `include/` | Core PHP: domain classes, ORM, config, staff/client views, plugins, i18n, PEAR, vendored libs |
| `scp/` | Staff Control Panel (agents/admins) — entry scripts + `staff.inc.php` / `admin.inc.php` |
| `api/` | HTTP API + email pipe / cron endpoints (`api.inc.php`, `http.php`, `pipe.php`, `cron.php`) |
| `setup/` | Installer + upgrade UI, SQL install stream, internal architecture docs (`setup/doc/`), static test runner |
| `css/`, `js/` | Shared client/staff CSS/JS (Font Awesome, Redactor, Select2, jQuery, pjax, …) |
| `images/`, `assets/` | Static images/favicon; themed assets (`assets/default/`) |
| `kb/` | Knowledge-base client routes (`faq.php`, …) |
| `pages/` | CMS-style public pages |
| `apps/` | Application registration surface (see `class.app.php`) |

Notable top-level files: `bootstrap.php` (paths/version/`Bootstrap` class),
`main.inc.php` (every request's entry sequence), `client.inc.php` /
`secure.inc.php` (client auth wrappers), `index.php`/`tickets.php`/`open.php`/
`view.php`/`account.php`/`profile.php`/`login.php` (client flows),
`ajax.php` (client AJAX dispatcher), `manage.php` (CLI management via
`php manage.php …`), `offline.php`, `file.php`/`logo.php`/`avatar.php`/`captcha.php`
(binary endpoints).

## Request lifecycle detail

**Client page** (e.g. `index.php`): `client.inc.php` → `main.inc.php` →
`bootstrap.php` constants + `Bootstrap::init()` → `loadConfig()` (reads
`include/ost-config.php`) → `defineTables(TABLE_PREFIX)` → `i18n_prep()` →
`loadCode()` (core helpers + `include/mysqli.php`) → `connect()` →
`osTicket::start()` (config, session, CSRF, company, plugin bootstrap, search)
→ client auth (`UserAuthenticationBackend`) → CSRF check on mutating methods
→ page logic + `include/client/*` views.

**Staff page** (e.g. `scp/tickets.php`): same through `main.inc.php`, then
`scp/staff.inc.php` → staff auth (`StaffAuthenticationBackend`) → offline
lockdown for non-admins → `include/staff/*` views.

**API** (`api/http.php`): `api.inc.php` → `main.inc.php` →
`include/class.dispatcher.php` URL routing → controllers (e.g.
`include/api.tickets.php`); plugins can add routes via
`Signal::send('api', $dispatcher)`.

## Database / ORM

- **Not ADODB.** Low-level DB access is procedural `mysqli` wrappers in
  `include/mysqli.php` (`db_connect`, `db_query`, …).
- **Custom ORM** in `include/class.orm.php` — Django-style (`VerySimpleModel`,
  `QuerySet`, `ModelMeta`, AND filters, join metadata). Design notes:
  `setup/doc/orm.md`.
- `include/class.model.php` (`ObjectModel`) is a separate type-code registry
  (`T` ticket, `S` staff, …) for polymorphic lookups — not the ORM base.
- Fresh-install schema: `setup/inc/streams/core/install-mysql.sql`
  (`%TABLE_PREFIX%` placeholders, typically `ost_`).
- Upgrade patches: `include/upgrader/streams/core/*.patch.sql` (~80 files,
  `{from8}-{to8}.patch.sql`, optional `.cleanup.sql`/`.task.php`). Engine:
  `include/class.migrater.php` + `include/class.upgrader.php`. Docs:
  `setup/doc/streams.md`.
- Runtime config: `include/ost-config.php` (generated; not for casual edits —
  `DBHOST`/`DBNAME`/`DBUSER`/`DBPASS`, `TABLE_PREFIX`, `SECRET_SALT`,
  `OSTINSTALLED`). Template: `include/ost-sampleconfig.php`. Missing file
  redirects to `setup/`.

## Core domain classes (`include/class.*.php`)

~94 class files. The ones worth knowing first:

| File | What it is |
|------|------------|
| `class.ticket.php` | Central Ticket model/workflow (create, assign, reply, status, SLA hooks) |
| `class.thread.php` / `class.thread_actions.php` | Conversation thread entries + actions |
| `class.staff.php` | Agent accounts, permissions, staff session identity |
| `class.client.php` / `class.user.php` | End-user identity (portal client vs. underlying `User`) |
| `class.organization.php` | Customer organizations |
| `class.dept.php` / `class.team.php` / `class.role.php` | Departments, teams, RBAC roles |
| `class.sla.php` / `class.schedule.php` / `class.businesshours.php` | SLA plans, business schedules, business-hours math |
| `class.topic.php` | Help topics |
| `class.email.php` / `class.mail.php` / `class.mailer.php` / `class.mailfetch.php` / `class.mailparse.php` | Email accounts/routing + compose/send/fetch/parse stack |
| `class.template.php` | Email message templates |
| `class.filter.php` / `class.filter_action.php` | Ticket filters + actions |
| `class.faq.php` / `class.category.php` / `class.knowledgebase.php` | Knowledge base |
| `class.canned.php` | Canned replies |
| `class.task.php` | Internal tasks linked to tickets |
| `class.queue.php` | Custom ticket queues / saved searches |
| `class.dynamic_forms.php` / `class.forms.php` / `class.list.php` | Dynamic forms & custom lists |
| `class.file.php` / `class.attachment.php` | File storage / attachments |
| `class.config.php` | DB-backed system config |
| `class.osticket.php` | System facade: config, session, CSRF, plugins, logging |
| `class.plugin.php` | Plugin manager + plugin/instance models |
| `class.auth.php` / `class.2fa.php` / `class.oauth2.php` | Auth backends, 2FA, OAuth2 |
| `class.api.php` / `class.ajax.php` / `class.controller.php` / `class.dispatcher.php` | API/AJAX controllers + URL dispatcher |
| `class.cron.php` | Cron job entry points |
| `class.signal.php` | Pub/sub extensibility hooks |
| `class.orm.php` / `class.migrater.php` / `class.upgrader.php` / `class.setup.php` | ORM, DB migrater, upgrader, setup wizard |
| `class.pdf.php` | PDF export (mPDF) |
| `class.csrf.php` / `class.validator.php` / `class.format.php` / `class.http.php` / `class.misc.php` | Cross-cutting helpers |
| `class.crypto.php` / `class.passwd.php` | Encryption / password hashing (phpass) |

## Templates / views

No Smarty/Twig — plain PHP includes.

| Area | Path | Pattern |
|------|------|---------|
| Staff pages | `include/staff/*.inc.php` (~73) | Full page fragments; `scp/` controllers do `require STAFFINC_DIR.'header.inc.php'` → page include → `footer.inc.php` |
| Staff partials | `include/staff/templates/*.tmpl.php` (~107) | Modals, queue UI, thread previews, forms |
| Client pages | `include/client/*.inc.php` | Landing, ticket open/view, KB, login, profile |
| Client partials | `include/client/templates/*.tmpl.php` (~8) | Sidebar, thread, dynamic form, print |
| Setup UI | `setup/inc/*.inc.php` | Installer screens |

File header convention: `vim: expandtab sw=4 ts=4 sts=4` (4-space indent).

## Plugins

- Install dir: `include/plugins/` (stock tree is nearly empty — `.keep` +
  `updates.pem` for signature verification).
- Each plugin is a directory or `.phar` with a `plugin.php` returning an info
  array (`PluginManager::allInfos()`).
- Lifecycle: `osTicket::start()` → `PluginManager::bootstrap()` → per
  installed+compatible plugin `init()` → per active instance
  `PluginInstance::bootstrap()` → plugin's own `bootstrap()`.
- Persistence: `PLUGIN_TABLE` / `PLUGIN_INSTANCE_TABLE`; config via
  `PluginConfig`.
- Extensibility: `Signal::connect` / `Signal::send` (`setup/doc/signals.md`);
  plugins can add API routes, auth backends, etc.
- Admin UI: `scp/plugins.php`, `include/staff/plugins.inc.php`, `plugin.inc.php`.

## Testing (PHP / stock app)

- No PHPUnit, no root `phpunit.xml`, no root `composer.json` test scripts.
- `setup/test/run-tests.php` — custom CLI harness running
  `setup/test/tests/test.*.php`, mostly **static/hygiene checks**: PHP syntax,
  short-open-tag usage, git-conflict markers, signal pairing, crypto, mail
  parsing, validation, jslint, etc. Not behavioral unit tests.
- PHPUnit configs under `include/mpdf/…` belong to the vendored mPDF library,
  not the app.
- The demo's own parity harness (`legacy/harness/`, `orchestrator/fixtures/`)
  is the closest thing to behavioral regression testing in this repo — but it
  only covers the specific seams under active migration (see `demo-overlay.md`).

## Dependency management

- No root `composer.json` for the app itself — dependencies are vendored in-tree.
- Composer-managed vendors — **do not touch unless a ticket explicitly
  requires it**:
  - `include/mpdf/` — mPDF `^8.0` + its `vendor/` (PDF export)
  - `include/laminas-mail/` — Laminas Mail + `vendor/` (email)
- Other bundled libs: `include/pear/` (Mail, Net_SMTP, …), `include/fpdf/`,
  `htmLawed.php`, `PasswordHash.php`, `Spyc.php`, `include/i18n/`, plus
  Font Awesome/jQuery/Redactor under `css/`/`js/`.

## Coding conventions

- 4-space indent, `expandtab sw=4 ts=4 sts=4` per file headers.
- Informal/historical PHP style — procedural entry scripts + class files, not
  PSR-12 enforced. No `.editorconfig` or PHPCS config at repo root.
- i18n: wrap new user-facing strings per `setup/doc/i18n.md`
  (gettext-style `__()`); translations flow through Crowdin per `README.md`.
- Internal architecture docs for contributors live under `setup/doc/`:
  `orm.md`, `signals.md`, `streams.md`, `forms.md`, `api.md`, `package.md`,
  `i18n.md` — read the relevant one before touching that subsystem.
