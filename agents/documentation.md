# Documentation

Documentation lives in two places with no overlap between them.

## README.md

The root README covers the repository, not the library. It contains, in order:

1. A short description of the package.
2. How to install the package with Composer, and its requirements.
3. How to set up the local development environment.
4. How to run the tests.
5. How to run the linters, formatter, and static analyzer.
6. Where to report a vulnerability, linking to `SECURITY.md`.
7. The license, linking to `LICENSE`.

Usage, options, and API documentation do not belong in the README. It links to
`docs` instead.

## SECURITY.md

The root `SECURITY.md` states which versions are supported, how to report a
vulnerability privately, and what is in and out of scope. Reports go through
GitHub's private advisory form; do not publish an email address as the reporting
channel. Keep the scope section grounded in what the package actually defends
against, and update it when those defences change.

## docs/

The `docs` directory holds the usage documentation as markdown files. Another
package reads these files and builds a documentation website with Docusaurus, so
write them as if they are already part of a Docusaurus site.

That means:

- Every file starts with YAML front matter containing `id`, `title`,
  `sidebar_position`, and `description`. Add `sidebar_label` when the sidebar
  needs a shorter title than the page.
- Group related pages in a subdirectory with a `_category_.json` that sets
  `label`, `position`, and a `generated-index` link.
- Link between pages with relative paths that include the `.md` extension, so
  Docusaurus can resolve and validate them.
- Use admonitions (`:::note`, `:::tip`, `:::caution`, `:::danger`) for caveats
  instead of bolded prose.
- Fence every code block with its language.
- Keep the content MDX-safe: wrap generics, array shapes, and anything
  containing `<` or `{` in backticks, or MDX parses it as JSX.

Document how the package is used, which options exist, and what each option
means. Options belong in a table with their type, default, and meaning. Say why
a default is what it is when the reason is not obvious, and document the
behaviour that will surprise someone before they hit it.

Keep the documentation truthful against the source. When behaviour changes,
update the page that describes it in the same commit.
