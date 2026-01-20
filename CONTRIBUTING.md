# Contributing to Agent State Language

Thank you for your interest in contributing to Agent State Language! This document provides guidelines and information for contributors.

## Code of Conduct

Please be respectful and constructive in all interactions. We're building something together.

## How to Contribute

### Reporting Issues

- Check existing issues before creating a new one
- Use clear, descriptive titles
- Provide reproduction steps when reporting bugs
- Include your PHP version and environment details

### Proposing Changes

1. **Fork** the repository
2. **Create a branch** for your changes (`git checkout -b feature/my-feature`)
3. **Make your changes** with clear commit messages
4. **Add tests** for new functionality
5. **Run the test suite** (`composer test`)
6. **Submit a Pull Request**

### Types of Contributions

#### Specification Improvements

- Clarifications to existing documentation
- New state types or extensions
- Examples and use cases

#### Implementation Enhancements

- Bug fixes
- Performance improvements
- New features matching the specification

#### Documentation

- Tutorial improvements
- New guides
- Translation efforts

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/agent-state-language.git
cd agent-state-language

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer cs-check
```

## Coding Standards

- Follow PSR-12 coding style
- Write PHPDoc comments for public methods
- Include type hints for parameters and return values
- Write tests for new functionality

## Commit Messages

Use clear, descriptive commit messages:

```
feat: Add support for timeout in Approval state
fix: Correct JSONPath evaluation for nested arrays
docs: Improve tutorial 3 with better examples
test: Add unit tests for MapState iterator
```

## Pull Request Process

1. Update documentation if needed
2. Add tests for new features
3. Ensure all tests pass
4. Request review from maintainers
5. Address feedback promptly

## Questions?

Open a Discussion or Issue if you need help or have questions about contributing.

Thank you for helping improve Agent State Language!
