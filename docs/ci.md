# Continuous Integration

The most common use case for this library is to bundle libraries during release
preparations before uploading an extension to
[TYPO3 Extension Repository (TER)](https://extensions.typo3.org/). This mostly
happens in an automated way using Continuous Integration (CI).

> [!NOTE]
> The following examples assume you require the `eliashaeussler/typo3-vendor-bundler`
> package in the `composer.json` file of your extension.

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
          php-version: 8.5
          tools: composer:v2, typo3/tailor
          coverage: none

      - name: Install Composer dependencies
        uses: ramsey/composer-install@v3

      - name: Bundle vendor libraries
        run: composer bundle -v

      - name: Stash bundled files
        run: |
          git add -f Resources/Private/Libs
          git stash push -- composer.json Resources/Private/Libs

      - name: Reset files
        run: git reset --hard HEAD && git clean -dfx

      - name: Restore bundled files
        run: git stash pop

      - name: Publish to TER
        run: |
          ~/.composer/vendor/bin/tailor set-version "${{ github.ref_name }}"
          ~/.composer/vendor/bin/tailor ter:publish "${{ github.ref_name }}" "${{ secrets.TYPO3_EXTENSION_KEY }}"
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
    # Install Composer dependencies
    - composer install

    # Bundle vendor libraries
    - composer bundle

    # Stash bundled files
    - git add -f Resources/Private/Libs
    - git stash push -- composer.json Resources/Private/Libs

    # Reset files
    - git reset --hard HEAD && git clean -dfx

    # Restore bundled files
    - git stash pop

    # Publish to TER
    - /tmp/vendor/bin/tailor set-version "$CI_COMMIT_TAG"
    - /tmp/vendor/bin/tailor ter:publish "$CI_COMMIT_TAG" "$TYPO3_EXTENSION_KEY"
```
