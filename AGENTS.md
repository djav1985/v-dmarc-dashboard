# AGENTS.md

## Purpose
This document defines baseline expectations for all contributors to maintain code quality, test coverage, and project stability.

---

## Framework Vision
- The `v-php-framework` is designed to be lightweight, modular, and developer-friendly.
- It uses **SPECTRE.CSS** as its foundational CSS framework for UI consistency and simplicity.  
  **Agents must not replace, remove, or override SPECTRE.CSS as the primary style system.**
- All contributions should prioritize maintainability, extensibility, and performance.

---

## Agent Development Principles
1. **Respect Core Architecture**
   - Do not refactor or replace major framework components unless requested by maintainers.
   - Agents should integrate smoothly with existing systems, not bypass or override them.
   - Do not change how any of the current `root/app/Core/` files operate.  
     Small improvements or adding new core files are fine, but do not completely replace them.
   - The same rule applies to the **Login Controller**—incremental changes are acceptable, but its core functionality must remain intact.

2. **Preserve UI Consistency**
   - All agent-generated UI must rely on SPECTRE.CSS classes and patterns.
   - Do not introduce alternate CSS frameworks, reset styles, or global overrides that conflict with SPECTRE.CSS.

3. **Incremental Improvements**
   - Focus on enhancements, bug fixes, and new features that fit within the current roadmap.
   - Avoid rewriting large sections of code or introducing breaking changes.

4. **Consistency**
   - Adhere to the framework’s coding standards and design patterns.
   - Maintain consistency in API design, naming conventions, and documentation.

5. **Minimal Disruption**
   - Avoid dependencies that significantly increase complexity or change the framework’s deployment requirements.
   - Test all changes for backward compatibility.

6. **Collaboration**
   - Discuss significant agent proposals with maintainers before implementation.
   - Submit RFCs (Request for Comments) for major changes.

---

## What Agents Should NOT Do
- Do not introduce alternate architectures (such as replacing MVC with another paradigm).
- Do not enforce opinionated choices (e.g., forcing use of a specific ORM, template engine, or library).
- Do not break existing APIs or remove key features without consensus.
- **Do not remove, override, or replace SPECTRE.CSS as the framework’s base style.**

---

## Development Guidelines

### 1. Testing Requirements
- **Always update or create tests** for any feature, bug fix, or refactor.
- Place new tests in the project’s designated test directory or add them to the existing test suite.
- Tests should fully cover the changes made, including edge cases.

### 2. Local Verification
Before committing changes:
1. **Run all tests**  
   Ensure the full test suite passes locally without errors.
2. **Lint the code**  
   Run the project's configured linter(s) or formatting tools.
3. **Fix all linting or formatting issues**  
   Use auto-fix tools when available, and manually address any remaining issues.

### 3. Change Workflow
- Implement changes in small, reviewable commits.
- Write clear and descriptive commit messages.
- Avoid pushing untested or lint-failing code.

### 4. Pull Request Expectations
- PRs must pass all automated checks (tests, linting, builds) before requesting review.
- Include a brief summary of the changes and their purpose.
- Reference related issues or tickets when applicable.

---

## Checklist Before Submitting Code
- [ ] Updated or created tests.
- [ ] All tests pass locally.
- [ ] Linting run with no errors.
- [ ] Commit messages are clear.
- [ ] PR description explains the "why" and "what."

---

## Notes
These are baseline expectations—projects may have additional requirements documented elsewhere. Always follow the most restrictive applicable rules.
