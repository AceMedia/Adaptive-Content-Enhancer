# Adaptive Content Enhancer

Block-authored forms and field groups for WordPress multisite. This is a local development build; keep Contact Form 7, CFDB7 and ACF active until migration checks are complete.

The original `ace-field-options.php` entry point remains for sites that activated that file previously. WordPress may show it as a separate **legacy entry** in the plugin list; activate only one entry per site.

## Forms

Insert the **Form** block in a page or synced pattern. Add **Field**, **Form step** and **Submit** blocks within it. A new form gets a UUID; saving the post registers its current definition. Existing CF7 forms can be imported into synced patterns. A field can display conditionally based on another field's value; the same rule applies during server validation. Multiple Form step blocks provide browser navigation, while all steps remain visible without JavaScript. Forms submit through `POST /ace-forms/v1/forms/{form_id}/entries` and without JavaScript through `admin-post.php?action=ace_form_submit`.

Saved forms appear under **Forms → Form patterns** and in WordPress's **Forms** pattern category. Editing a synced pattern updates its placements. The directory includes existing imported patterns as well as new patterns containing an ACE Form block. Frontend forms collect submissions; they are separate from field groups and editor content templates used to edit post meta.

The form inspector provides recipients, sender, subject, body, Reply-To, CC, BCC, customer confirmation, success text and **Input appearance**. Imported CF7 senders use `{site_domain}` or `{network_domain}` so a local `.pi` address resolves to the matching domain; invalid sender headers are ignored. Input appearance offers outlined, filled, underlined and soft controls; floating or standard labels; spacing; radius; and colours from the active theme palette. Floating labels move when a control is focused or filled. The forms use WordPress preset CSS variables on both the site and block editor. The **Product enquiry** variation includes a hidden product field populated from the current post title; field defaults can also come from a fixed value or URL parameter.

Available form controls include text, text area, email, telephone, URL, number, range, date, time, date and time, colour, select, radio, checkboxes, consent, hidden and file. File types and sizes are checked server-side. Turnstile is optional. With Customer Engagement active, current captures and delivery results appear under its **Forms** screen; the ACE **Forms** screen holds imported and fallback entries with CSV export. A local development mu-plugin intercepts `wp_mail()`, so a successful local mail log means the message was accepted by the local guard, not delivered.

Association fields can choose a published post or product, several related posts, a public taxonomy term, a taxonomy itself, or a public post type. Configure allowed post types or taxonomies in the Field block sidebar; leave them empty to use every public REST-enabled source. Visitors get a searchable list with a native select fallback, and submitted IDs or slugs are checked against the configured source before storage. User association remains an editor-only field and requires permission to list users.

## Custom fields

Field definitions are `ace_field_group` posts built from the **Field group**, **Field** and **Nested field** blocks. Set location rules in the group inspector. Post-type fields use registered WordPress post meta and appear in the post editor sidebar. Taxonomy fields use registered term meta and an ACE value panel on the term edit screen. Flexible ACF rows are stored with their original row meta keys and can be edited there. The PHP value API is `ace_get_field()`, `ace_update_field()` and `ace_get_rows()`. Simple post meta can use the core `core/post-meta` block binding; ACE also registers `adaptive-content-enhancer/field` for other values.

Under **Fields → Editor templates**, an administrator can choose a published synced content pattern for each block-enabled post type and choose an unlocked, insertion-locked or fully locked editor. ACE assigns the pattern through WordPress's native post-type `template` and `template_lock` settings for new posts. Use bound blocks in that content pattern to display registered meta; edit field values through the ACE controls. Frontend form patterns are excluded from the template selector. Existing posts keep their content.

The optional `ace_fields_acf_compat` setting enables basic `get_field()`, `the_field()`, `have_rows()`, `the_row()` and `get_sub_field()` wrappers only when ACF has not already defined those functions. It is off by default. The original ACF field posts and meta values remain in place after import.

Imported ACF post-object, relationship, taxonomy and user fields now use searchable selectors in the post editor. Existing ACF source restrictions are read from their imported settings; new fields use the association settings in the Field block sidebar. Values remain native WordPress IDs or post-type slugs in registered meta, and `ace_get_field()` can still format them as objects.

## Local imports and checks

The **Ace Content** dashboard groups forms, field groups, entry archives, editor templates, field options and settings under one WordPress admin heading. **Import ACF** and **Import CF7** are separate five-stage onboarding screens: detect, preview, adopt, verify and optionally retire. ACF definitions become block-authored field groups while existing post and term values stay in WordPress meta. CF7 forms become synced patterns; CFDB7 entry history can then be transferred to Adaptive Customer Engagement. The screens check adopted counts and source plugin status before offering retirement. Deletion requires a separate typed confirmation. The earlier URLs redirect to the new import screens, and WP-CLI commands remain available.

The settings screen uses a persistent save bar with optional auto-save. The WordPress Settings API remains the non-JavaScript fallback for Turnstile keys and ACF compatibility. New submissions go directly to Customer Engagement when it is active on the site; ACE's entry archive retains earlier imports and serves as a fallback if that storage provider is unavailable.

Back up the local database first. WP-CLI uses the current `--url` site in this multisite network.

```bash
wp ace-forms import-cf7 --dry-run --replace-shortcodes --url=https://iegemea.pi
wp ace-forms import-cf7 --replace-shortcodes --url=https://iegemea.pi
wp ace-forms import-cf7 --refresh-notifications --url=https://iegemea.pi
wp ace-forms import-cfdb7 --dry-run --url=https://iegemea.pi
wp ace-forms import-cfdb7 --url=https://iegemea.pi
wp ace-fields import-acf --dry-run --url=https://iegemea.pi
wp ace-fields import-acf --url=https://iegemea.pi
```

The CF7 importer preserves existing synced pattern fields and appearance when refreshing notifications. It only replaces placements that match an existing valid imported pattern. Re-running the CFDB7 importer should not duplicate entries. The ACF importer creates a recovery group for orphaned source fields instead of discarding them. It skips groups already imported by default; use `--group=<key> --overwrite` to refresh a specific group from ACF and retain a revision.

On this local network, the shared product template uses a registered `core/pattern` placeholder that resolves to the site's synced product enquiry form through `core/block`. IEG uses the imported QUOTE pattern; Taylor Bins uses its Contact Us pattern. The three stale CF7-only inline style blocks were removed from local pages with revisions retained. Main, shop and dev use Cloudflare's public test Turnstile keys in their local database, so browser submissions work on `.pi` domains. Set real site-specific keys before enabling Turnstile on live.

The gitignored local safety mu-plugin also suppresses CF7's old reCAPTCHA asset and temporarily preserves imported Customer Engagement form history during scheduled local retention runs. This only affects the Pi mirror. Decide the live retention period before deployment; Customer Engagement's current setting is 730 days.

Local editor links: [IEG contact](https://iegemea.pi/wp-admin/post.php?post=1749&action=edit), [IEG quote pattern](https://iegemea.pi/wp-admin/post.php?post=15606&action=edit), [Taylor Bins contact pattern](https://egberttaylor.pi/wp-admin/post.php?post=984&action=edit). The local mail guard suppresses outbound messages even when `wp_mail()` reports success.

## Extension points

The submission pipeline detects Adaptive Customer Engagement through the `ace_forms_storage_provider` filter. When active, Customer Engagement stores the capture, session and company links, notification outcomes, confirmation outcome, and private upload references. ACE Forms continues to own the blocks, validation, uploads, notifications and authenticated download route. The capture is saved before notifications are sent, then delivery results are written back to its record. When Customer Engagement is inactive or cannot save a submission, ACE Forms stores a complete local entry. Earlier ACE and CFDB7-imported entries remain in the ACE entries archive; Customer Engagement offers an idempotent command to copy CFDB7 history into its reporting store. The `ace_forms_entry_created` and `ace_form_submitted` actions still fire once; their context includes `storage_provider` and the latter no longer causes a duplicate Customer Engagement row. The browser fires `ace-forms:submitted` and the earlier `ace:form-submitted` event, and pushes `ace_form_submit` to `dataLayer` when present. Additional field types can register with `ace_field_types_register`.

WordPress Abilities API exposes the read-only `adaptive-content-enhancer/list-forms` and `adaptive-content-enhancer/list-field-groups` abilities to users with the matching ACE capabilities. Anonymous callers cannot execute them.

## Current migration limits

The current importer handles the ACF definitions and values found on the local IEG main and dev sites, including post and taxonomy groups. The local main and dev Company News pages now use the native recent-posts block, and unregistered zero-output `acf/user-login` blocks were removed from their DB-overridden headers with revisions retained. No active ACF block instances remain on the local main, dev or shop sites; any newly discovered ACF block with live field data or a render callback still needs a dedicated migration. Source CF7 definitions missing from the local database cannot be reconstructed from this importer. Location rules beyond the supported editor contexts, user/options value editing, richer relationship pickers, retention controls, asynchronous integrations and DataViews for entries remain to be finished before switching off the source plugins.

## Build

```bash
npm ci
npm run build
```

The block scripts and styles in `src/blocks` build into `build/blocks`; editor and admin components build separately. Do not edit generated files directly.
