# HTTP API Surface

This package does not register controller routes or expose inbound HTTP
endpoints, so it has no OpenAPI paths to publish. Applications using the
package must define their own validation, confirmation, and STK callback
controllers and include those host-application routes in their OpenAPI
document.

The package's outbound public PHP API is documented in the root
[`README.md`](../../README.md#public-api).
