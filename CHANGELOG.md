# Changelog

All notable changes to Agent State Language will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
