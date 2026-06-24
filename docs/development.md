# Development

Contributing and developing the Laravel M-Pesa package.

## Getting Started

### Clone the Repository

```bash
git clone https://github.com/clipsmm/laravel-mpesa.git
cd laravel-mpesa
```

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
composer test
```

## Development Workflow

### 1. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

### 2. Make Your Changes

Follow the coding standards:

```bash
# Auto-fix code style
composer format

# Run static analysis
composer analyse

# Run tests
composer test
```

### 3. Write Tests

Add tests for new features:

```php
// tests/Unit/YourFeatureTest.php
namespace LaravelMpesa\Tests\Unit;

use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class YourFeatureTest extends TestCase
{
    #[Test]
    public function it_does_something()
    {
        // Arrange
        // Act
        // Assert
    }
}
```

### 4. Update Documentation

Update relevant documentation:
- `README.md` - If adding public API
- `CHANGELOG.md` - Under `[Unreleased]`
- `docs/` - If adding new features

### 5. Submit Pull Request

```bash
git push origin feature/your-feature-name
```

Then open a PR on GitHub.

## Code Quality

### Static Analysis

Uses PHPStan Level 8:

```bash
composer analyse
```

Fix issues before committing.

### Code Style

Uses Laravel Pint:

```bash
# Check style
composer format:test

# Auto-fix style
composer format
```

### Testing

Maintain high test coverage:

```bash
# Run tests
composer test

# Generate coverage
composer test:coverage

# View coverage
open coverage/index.html
```

## Package Structure

```
laravel-mpesa/
├── src/               # Source code
│   ├── Responses/     # Response DTOs
│   ├── Traits/        # Reusable traits
│   └── *.php          # Core classes
├── tests/             # Test suite
│   ├── Unit/          # Unit tests
│   └── TestCase.php   # Base test class
├── config/            # Configuration
├── docs/              # Documentation
├── .github/           # GitHub workflows
├── composer.json      # Dependencies
├── phpstan.neon       # Static analysis config
├── pint.json          # Code style config
└── phpunit.xml        # Test configuration
```

## Adding New Features

### Example: Adding a New API Method

1. **Add method to RequestManager:**

```php
// src/RequestManager.php
public function newMethod(
    string $param,
    bool $returnDto = false,
): array|NewResponse {
    // Validate parameters
    if ($param === '') {
        throw new InvalidArgumentException('Param cannot be empty');
    }
    
    // Send request
    [$successful, $data] = $this->send(
        $this->getEndpoint('/mpesa/new/v1/endpoint'),
        ['Param' => $param]
    );
    
    // Return DTO or array
    return $returnDto 
        ? NewResponse::fromArray($successful, $data)
        : [$successful, $data];
}
```

2. **Create Response DTO:**

```php
// src/Responses/NewResponse.php
namespace LaravelMpesa\Responses;

readonly class NewResponse extends MpesaResponse
{
    public function __construct(
        bool $successful,
        array $rawResponse,
        ?string $errorMessage = null,
        public ?string $newField = null,
    ) {
        parent::__construct($successful, $rawResponse, $errorMessage);
    }

    public static function fromArray(bool $successful, array $data): self
    {
        return new self(
            successful: $successful,
            rawResponse: $data,
            errorMessage: $successful ? null : ($data['errorMessage'] ?? 'Unknown error'),
            newField: $data['NewField'] ?? null,
        );
    }

    public function getNewField(): ?string
    {
        return $this->newField;
    }
}
```

3. **Write Tests:**

```php
// tests/Unit/NewMethodTest.php
#[Test]
public function it_calls_new_method()
{
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3599,
        ]),
        '*/mpesa/new/v1/endpoint' => Http::response([
            'NewField' => 'value',
        ]),
    ]);

    $response = MpesaSdk::instance()->newMethod('param', returnDto: true);

    $this->assertTrue($response->isSuccessful());
    $this->assertEquals('value', $response->getNewField());
}
```

4. **Update Documentation:**

```markdown
<!-- docs/new-feature.md -->
# New Feature

Description...

## Usage

\`\`\`php
$response = $mpesa->newMethod('param', returnDto: true);
\`\`\`
```

## Release Process

### 1. Update Version

```php
// composer.json
{
    "version": "0.2.0"
}
```

### 2. Update Changelog

```markdown
## [0.2.0] - 2024-XX-XX

### Added
- New feature description

### Changed
- Changed feature description

### Fixed
- Bug fix description
```

### 3. Create Release

```bash
# Using release script
composer release:minor

# Or manually
git tag v0.2.0
git push origin v0.2.0
```

## Documentation

### Local Preview

```bash
# Serve docs locally
cd docs
python3 -m http.server 8000

# Open browser
open http://localhost:8000
```

### Deploy Documentation

Documentation auto-deploys when pushing to `main`:

```bash
git push origin main
```

View at: https://clipsmm.github.io/laravel-mpesa/

## Debugging

### Enable Xdebug

```bash
# Install Xdebug
pecl install xdebug

# Enable in PHP
php -dxdebug.mode=debug vendor/bin/phpunit
```

### Laravel Tinker

```bash
php artisan tinker

>>> $mpesa = app(\LaravelMpesa\MpesaSdk::class);
>>> $mpesa->getConfig('shortcode');
```

### Dump HTTP Requests

```php
Http::fake();

// Make request
$mpesa->stkPush(...);

// Dump requests
Http::recorded(function ($request, $response) {
    dump($request->url());
    dump($request->body());
});
```

## CI/CD

### GitHub Actions

Tests run automatically on:
- Push to `main`
- Pull requests
- PHP 8.2, 8.3, 8.4
- Laravel 12.x, 13.x

### Local CI Simulation

```bash
# Run all checks
composer format:test && composer analyse && composer test
```

## Getting Help

- Read [CONTRIBUTING.md](../CONTRIBUTING.md)
- Check [existing issues](https://github.com/clipsmm/laravel-mpesa/issues)
- Ask in [discussions](https://github.com/clipsmm/laravel-mpesa/discussions)

## Code of Conduct

Be respectful and inclusive. See [Code of Conduct](https://github.com/clipsmm/laravel-mpesa/blob/main/CODE_OF_CONDUCT.md).

## License

MIT License - see [LICENSE](https://github.com/clipsmm/laravel-mpesa/blob/main/LICENSE).
