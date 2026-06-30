# Annotations

## 2026-07-01: GitHub Actions Node.js 20 deprecation

- `actions/checkout@v4` and `softprops/action-gh-release@v2` use Node.js 20,
  which GitHub Actions deprecated and now forces onto Node.js 24.
- Fixed by bumping: `actions/checkout@v4` → `v7`,
  `softprops/action-gh-release@v2` → `v3`.
- Files affected: `.github/workflows/readme.yml`, `.github/workflows/release.yml`.
