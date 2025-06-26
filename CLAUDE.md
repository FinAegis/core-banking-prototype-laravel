# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 📍 Main Documentation

The comprehensive development guide for Claude Code has moved to:

**[→ docs/06-DEVELOPMENT/CLAUDE.md](docs/06-DEVELOPMENT/CLAUDE.md)**

This includes:
- Development commands and workflows
- Architecture overview and patterns
- Testing strategies
- Code examples and best practices
- Implementation phase details
- Important file locations

Please refer to the main documentation for all development guidance.

## 📋 Local TODO List (Optional)

If a `TODO.md` file exists in the project root, read it at the start of each session for current task tracking and context. This is a local file (gitignored) used for session continuity.

---

### Quick Command Reference

```bash
# Run tests
./vendor/bin/pest --parallel

# Create admin user
php artisan make:filament-user

# Start development server
php artisan serve

# Run queue workers
php artisan queue:work --queue=events,ledger,transactions
```

For the complete command reference and detailed instructions, see the main documentation.