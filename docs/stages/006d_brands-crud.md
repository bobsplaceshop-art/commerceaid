
---

## Addendum — Validation Overhaul

After shipping Brands CRUD, an audit revealed two classes of
validation problems across the platform. Both are now fixed.

### Problem 1: URL validation was too lenient

`filter_var($url, FILTER_VALIDATE_URL)` accepts any string with a
scheme-like prefix, including `htp://invalid`. A typo saved without
error in testing.

### Problem 2: showCreate() did not read flash data

Brand and Category controllers wrote validation errors and old
input to the session flash but `showCreate()` passed empty arrays
to the view. Result: form silently cleared on validation failure
with no error messages shown.

### The Fix

**`app/Commerce/Support/Validator.php`** — new shared validator with:

- `required`, `maxLength`, `minLength`, `pattern`
- `url` (strict http/https + dotted host + filter_var)
- `email`, `inArray`, `timezone` (IANA list check)
- `positiveInt`, `nonNegativeNumber`
- `hexColor`, `countryCode`, `slug`

Every controller now validates through this helper. Every form
field enforces max length matching its DB column.

**`showCreate()` fixes** — Brand and Category controllers now read
`$session->getFlash('*_errors', [])` and `$_SESSION['_old'] ?? []`
exactly like `showEdit()`. Form re-population and error rendering
now work on validation failure.

### Audit Results — 18 Test Cases

All passed:

- URL scheme typo (`htp://`) rejected
- URL without TLD rejected
- Name length overflow rejected
- Slug length overflow rejected
- Uppercase slug rejected
- Invalid IANA timezone rejected
- Invalid country code rejected
- Invalid hex colour rejected
- Hex colour without `#` rejected
- Invalid email format rejected
- Empty email rejected
- Empty password rejected
- All valid inputs still save

### Going Forward

New controllers must use `Validator` for field checks. A future
refactor may extract form-rendering boilerplate into a helper on
`AdminController`, but Categories and Brands already work and will
not be changed retroactively.
