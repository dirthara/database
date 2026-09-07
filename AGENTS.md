# Project instructions

## Ownership
Dirthara owns this package. Attribute copyright, licensing, and authorship to
`Dirthara` rather than to an individual maintainer. The MIT `LICENSE` reads
`Copyright (c) <year> Dirthara`, and new files or documents that name an owner
use the same name.

## Branching
Every supported version has its own branch; there is no `main`. Target a feature at
the newest release branch and a fix at the earliest supported branch that has the bug,
then forward-merge upward. Read [CONTRIBUTING.md](CONTRIBUTING.md) before branching,
merging, or releasing.

## Tests
Line coverage of `src` must stay at 100%; `composer coverage` fails below it and lists
the uncovered lines. Behaviour that needs a real database belongs in the shared
conformance suite in `tests/Integration`, not in a copy per driver.

## Exceptions
Read and follow [exception conventions](agents/exceptions.md) when creating or modifying exceptions.

## Documentation
Read and follow [documentation conventions](agents/documentation.md) when writing the README or anything in `docs`.
