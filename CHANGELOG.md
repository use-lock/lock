# Changelog

## 0.1.0 (2026-10-22)


### Features

* export admin auth and management API groups ([#6](https://github.com/use-lock/lock/issues/6)) ([20eec6e](https://github.com/use-lock/lock/commit/20eec6e82787af4b06b0c2c8f5aa0c20ad179a6c))
* expose named OAuth grant schemas for generated clients ([ad081cf](https://github.com/use-lock/lock/commit/ad081cf631bf8a2c825e676b60e2bdd7bd5bb2e5))
* initialize Lock identity provider ([bb913c4](https://github.com/use-lock/lock/commit/bb913c448a6b5582b568ea78f17100432a617f72))
* offer PATCH only for API updates ([1577e45](https://github.com/use-lock/lock/commit/1577e451478baffe14a89c6236fbfd427b4eca9a))
* reorder the realm sidebar and drop its duplicate admin events entry ([72f10ec](https://github.com/use-lock/lock/commit/72f10ec55ca20301b6ceaad6543078a351c226b5))
* replace the Laravel logo with the Lock padlock ([#19](https://github.com/use-lock/lock/issues/19)) ([7b8534d](https://github.com/use-lock/lock/commit/7b8534d876b5047bb66affab6391b3b99abcd69d))
* send security headers and rate limit the management API and account console ([8d7e03e](https://github.com/use-lock/lock/commit/8d7e03e3189f09d9eb036405416963a8958a4b90))
* set client revocation through the management API ([31ac29d](https://github.com/use-lock/lock/commit/31ac29da951d615cb4f9c2e74deb0c4ad39135aa))
* version the API documents with the app release ([145f783](https://github.com/use-lock/lock/commit/145f78359e3d09d01977f2c098842ad95c7f5127))


### Bug Fixes

* grant the console client the Admin and Management API audiences ([6600fc9](https://github.com/use-lock/lock/commit/6600fc9a34981362c8d2cbaa39908c6f11304e20))
* handle release checkouts and generated changelogs ([#4](https://github.com/use-lock/lock/issues/4)) ([872face](https://github.com/use-lock/lock/commit/872facef406ca0760135b2ba556abfadf5e0df63))
* serve the committed OpenAPI documents instead of caching Scramble on deploy ([de547db](https://github.com/use-lock/lock/commit/de547dbecce1c5b8532ca85f1ae66bf989e2175b))
