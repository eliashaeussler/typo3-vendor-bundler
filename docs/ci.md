# Continuous Integration

The most common use case for this library is to bundle libraries during release
preparations before uploading an extension to
[TYPO3 Extension Repository (TER)](https://extensions.typo3.org/). This mostly
happens in an automated way using Continuous Integration (CI).

## GitHub Actions

Assuming you have a single release workflow called `release.yaml`, you can
integrate the library like follows:

```yaml
# .github/workflows/release.yaml

name: Release
on:
  push:
    tags:
      - '*'

jobs:
  ter-publish:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          tools: composer:v2, eliashaeussler/typo3-vendor-bundler, typo3/tailor
          coverage: none

      - name: Cleanup files
        run: git reset --hard HEAD && git clean -fx

      - name: Bundle vendor libraries
        run: composer bundle

      - name: Publish to TER
        run: ~/.composer/vendor/bin/tailor ter:publish "${{ github.ref_name }}" "${{ secrets.TYPO3_EXTENSION_KEY }}"
```

## GitLab CI

When using GitLab CI, extend the release job of your `.gitlab-ci.yml` file
like follows:

```yaml
# .gitlab-ci.yml

release:
  stage: release
  image: composer:2
  rules:
    - if: '$CI_COMMIT_TAG'
  script:
    # Require libraries
    - composer global require eliashaeussler/typo3-vendor-bundler typo3/tailor

    # Cleanup files
    - git reset --hard HEAD && git clean -fx

    # Bundle vendor libraries
    - composer bundle

    # Publish to TER
    - /tmp/vendor/bin/tailor ter:publish "$CI_COMMIT_TAG" "$TYPO3_EXTENSION_KEY"
```
