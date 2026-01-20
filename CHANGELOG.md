# Changelog

All notable changes to Agent State Language will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.1] - 2026-01-21

### Added
- Documentation for `ApprovalHandlerInterface` implementation
- Documentation for workflow pause/resume patterns
- Production deployment guide for human-in-the-loop workflows
- REST API integration examples for approval workflows

### Changed
- Updated Tutorial 08 (Human Approval) with complete implementation examples
- Updated state-types reference with Editable fields and handler integration
- Updated production-deployment guide with lifecycle callbacks

## [0.3.0] - 2026-01-21

### Added
- **Human-in-the-Loop Support**: Complete approval workflow implementation
  - `ApprovalHandlerInterface` for custom approval integrations
  - `ExecutionPausedException` for pausing workflows awaiting input
  - Pause/resume support in `WorkflowEngine` and `WorkflowResult`
- State lifecycle callbacks: `onStateEnter()` and `onStateExit()` hooks
- Checkpoint data management for workflow resumption
- `WorkflowEngine::fromArray()` factory method
- `WorkflowResult::paused()` static constructor for paused workflows
- Resume data support in execution context

### Changed
- `ApprovalState` now integrates with `ApprovalHandlerInterface` for real approval workflows
- `WorkflowEngine::run()` now accepts optional `$startFromState` and `$resumeData` parameters
- `WorkflowResult` constructor now includes pause-related parameters
- Improved code style consistency across codebase

### Fixed
- Trailing whitespace and code formatting issues

## [0.2.1] - 2026-01-21

### Changed
- Updated Claude model identifiers to latest naming convention in documentation

### Fixed
- Code style formatting in integration tests

## [0.2.0] - 2026-01-20

### Added
- LLM Agent integration with Claude PHP Agent SDK
- `LLMAgentFactory` for configuring agents from workflow definitions
- `LLMAgentAdapter` implementing `AgentInterface` for LLM-powered states
- 5 new advanced tutorials (13-17):
  - Integrating Claude PHP Agent
  - Tool-enabled Agent Workflows
  - Multi-agent Orchestration
  - Loop Strategies in Workflows
  - RAG-enhanced Workflows
- Runnable example scripts for all workflow examples
- Loop strategies documentation and patterns
- Enhanced intrinsic functions for workflow expressions
- Guide and reference documentation updates

### Changed
- Expanded tutorial coverage from 12 to 17 tutorials
- Improved documentation code validation tests
- Enhanced best practices guide with LLM integration patterns

### Fixed
- Documentation code examples now fully validated
- Intrinsic function edge cases in expressions

## [0.1.0] - 2026-01-20

### Added
- Initial release
- Complete ASL specification
- PHP WorkflowEngine implementation
- Basic documentation and tutorials
