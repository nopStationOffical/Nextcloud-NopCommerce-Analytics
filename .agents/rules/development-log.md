# Development Log Requirements

## Mandatory Logging
Every step taken during planning, development, migration, testing, and deployment MUST be logged in [`DEVELOPMENT_LOG.md`](../../DEVELOPMENT_LOG.md) at the project root.

## Log Format
Use a markdown table with these columns:

| Column | Description |
|--------|-------------|
| **Timestamp** | ISO 8601 format with timezone (`2026-08-31T19:50:00+06:00`) |
| **Phase / Action** | What phase of work this belongs to (e.g., "Database Migration", "Frontend Build", "API Integration", "Deployment") |
| **Purpose & Goal** | Why this step is being taken and what it aims to achieve |
| **Modification / Output** | What was actually changed, created, or produced |
| **Tools Used** | Which tools/commands were executed (e.g., `write_to_file`, `run_command`, `view_file`) |
| **Files Created / Modified** | List of files affected by this step |

## Rules
1. Log BEFORE starting significant work (note intent).
2. Log AFTER completing each meaningful step (note outcome).
3. Include both success and failure outcomes.
4. Group related micro-steps under a single log entry when appropriate.
5. Never skip logging a deployment, migration, or test execution.
