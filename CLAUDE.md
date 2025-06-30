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

## 📋 Project TODO List

**IMPORTANT**: Always read `TODO.md` at the start of each session if it exists. This is a local file (gitignored) that contains all project tasks organized by feature branches, priorities, and completion status. It serves as session continuity between Claude Code interactions.

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