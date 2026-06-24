# Contributing to Laravel M-Pesa

Thank you for considering contributing to this package! This guide will help you get started.

## Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/clipsmm/laravel-mpesa.git
   cd laravel-mpesa
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Run tests**
   ```bash
   composer test
   ```

## Code Quality Standards

This package follows Laravel coding standards and best practices:

- **PSR-12** coding style (enforced by Laravel Pint)
- **PHPStan Level 8** static analysis
- **100% backward compatibility** for existing features
- **Comprehensive test coverage** for new features

### Before Submitting

Run these commands to ensure code quality:

```bash
composer format          # Auto-fix code style issues
composer format:test     # Check code style without fixing
composer analyse         # Run static analysis
composer test            # Run test suite
composer audit           # Check for security vulnerabilities
```

## Pull Request Process

1. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Write tests first**
   - Add tests for new functionality in `tests/Unit/`
   - Ensure tests are isolated and use HTTP fakes
   - Aim for high code coverage

3. **Implement your feature**
   - Follow existing code patterns
   - Add type hints and docblocks
   - Maintain backward compatibility

4. **Update documentation**
   - Update `README.md` if adding public API methods
   - Add usage examples for new features
   - Update `CHANGELOG.md` under `[Unreleased]`

5. **Ensure all checks pass**
   ```bash
   composer format
   composer analyse
   composer test
   ```

6. **Submit your PR**
   - Write a clear description of changes
   - Reference any related issues
   - Add `release:patch`, `release:minor`, or `release:major` label

## Testing Guidelines

- **Unit tests** should be fast and isolated
- **Use HTTP fakes** - never make real API calls
- **Mock external dependencies** (Log, Http, etc.)
- **Test edge cases** and error conditions
- **Assert behavior**, not implementation

### Example Test

```php
#[Test]
public function it_validates_input_before_sending_request(): void
{
    $manager = MpesaSdk::instance();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('expected error message');
    
    $manager->someMethod('invalid-input');
}
```

## Coding Conventions

### Type Safety

Always use strict types and provide type hints:

```php
declare(strict_types=1);

public function stkPush(
    string $receiver,
    int $amount,
    bool $returnDto = false,
): array|StkPushResponse {
    // ...
}
```

### Error Handling

- Throw `InvalidArgumentException` for invalid input
- Throw `RuntimeException` for unexpected states
- Return `false` only for expected failure conditions

### Backward Compatibility

- Never remove or rename public methods
- Add optional parameters at the end
- Use the `returnDto` pattern for new response types

## Adding New M-Pesa APIs

To add support for a new M-Pesa API endpoint:

1. **Create a response DTO** in `src/Responses/`
2. **Add the method** to `RequestManager.php`
3. **Support both array and DTO returns** via `returnDto` parameter
4. **Add comprehensive tests** in `tests/Unit/`
5. **Document the API** in `README.md`

### Example Implementation

```php
public function newMethod(
    string $param,
    bool $returnDto = false,
): array|NewResponse {
    $this->validateParam($param);
    
    [$successful, $data] = $this->send(
        $this->getEndpoint('/mpesa/new/v1/endpoint'),
        ['Param' => $param]
    );
    
    return $returnDto 
        ? NewResponse::fromArray($successful, $data)
        : [$successful, $data];
}
```

## Security

If you discover a security vulnerability:

1. **Do not open a public issue**
2. **Email the maintainer** directly
3. **Include detailed information** about the vulnerability
4. **Allow time** for a fix before public disclosure

## Questions?

- Check the [README](README.md) for usage examples
- Review existing code for patterns
- Open an issue for clarification

Thank you for contributing! 🎉
