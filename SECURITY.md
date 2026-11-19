# Security

Please report a vulnerability privately to **contact@satelit.md** (not in a public issue). Include the version, the CMS version and the steps to reproduce. We answer within a few working days and credit reporters who wish it.

Design notes:

- The API key stays on the server. It is never printed in a page, a log line or a URL. The settings field is always rendered empty.
- Every value from the API is escaped before output. Only `http(s)` links from the data are kept.
- Admin forms use the CMS's own CSRF tokens and permissions.
- Every HTTP call has a timeout (6 s, 3 s to connect on Drupal). A failure never breaks a page.
