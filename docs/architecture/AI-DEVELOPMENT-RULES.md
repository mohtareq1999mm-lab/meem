# AI Development Rules - Architecture First

## Mandatory Rule

Before modifying ANY code in this project, the AI agent MUST:

1. Read the architecture documentation inside:

docs/architecture/

2. Identify if there is an existing architecture decision related to the requested feature.

3. Understand the current implementation flow before writing code.

The AI agent is not allowed to directly implement changes without understanding the existing architecture.

---

## Required Workflow

Every development task must follow this order:

### Phase 1 - Discovery

Before coding:

- Search the codebase.
- Find existing implementation.
- Read related architecture documents.
- Identify existing services, strategies, resolvers, repositories, and flows.

No code changes are allowed during this phase.

---

### Phase 2 - Architecture Understanding

The AI must explain:

- Current execution flow.
- Existing classes involved.
- Current business logic location.
- Correct extension point.

Example:

Request

↓

Controller

↓

Application Service

↓

Domain Service

↓

Repository

↓

Resource

↓

Response

The AI must discover the real flow from the project.

---

### Phase 3 - Change Plan

Before implementation, the AI must provide:

- Files that will be modified.
- Reason for each modification.
- Why the change follows the current architecture.
- Confirmation that no duplicate business logic is created.

---

### Phase 4 - Implementation

Only after understanding the architecture:

The AI must:

- Reuse existing services.
- Reuse existing patterns.
- Extend current flows.
- Keep backward compatibility.

---

## Forbidden Actions

The AI must NOT:

- Create new services before checking existing ones.
- Duplicate business logic.
- Put business logic inside Resources.
- Put business logic inside Models.
- Put business logic inside Controllers.
- Create parallel flows for existing features.
- Refactor frozen architecture without approval.

---

## Frozen Architecture Rule

If any document inside:

docs/architecture/

contains:

Status: Frozen

The AI must treat it as a mandatory rule.

Frozen architecture can only change because of:

1. Verified production bug.
2. Failing automated test.
3. New business requirement that cannot be implemented otherwise.

Personal preference or cleaner design is not a valid reason.

---

## Final AI Principle

The AI must always follow:

Understand → Analyze → Plan → Modify

Never:

Modify → Discover
