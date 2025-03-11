# Contributing to Farzai Breaker

Thank you for considering contributing to Farzai Breaker! This document provides some basic guidelines to help you get started.

## Development Setup

1. Fork the repository
2. Clone your fork locally
3. Install dependencies:

```bash
composer install
```

## Testing

We use PHPUnit for testing. To run the test suite:

```bash
composer test
```

Make sure all tests pass before submitting your pull request.

## Coding Style

This project follows PSR-12 coding standards. We use PHP-CS-Fixer to enforce our coding style:

```bash
composer cs-fix
```

Also, we use PHPStan for static analysis:

```bash
composer phpstan
```

## Pull Request Process

1. Update the README.md with details of changes if appropriate
2. Update the example files if needed
3. Add tests for your new features or bug fixes
4. Ensure all tests pass
5. Submit a pull request

## Adding New Features

If you're adding new features:

1. Make sure they align with the project's goals
2. Add appropriate tests
3. Update documentation 
4. Consider backward compatibility
5. If it's a major feature, consider opening an issue first to discuss it

## Reporting Bugs

When reporting bugs, please include:

1. The PHP version you're using
2. Steps to reproduce the issue
3. Expected behavior
4. Actual behavior
5. Any relevant code snippets or error messages

## License

By contributing, you agree that your contributions will be licensed under the project's MIT License.