# AGENTS.md

## Purpose
This document sets baseline expectations for contributors to ensure code quality, test coverage, and project stability.

---

## Agent Development Principles

1. Core Integrity
- The `v-php-framework` is designed to be lightweight, modular, and developer-friendly.
- Respect existing architecture: do not refactor or replace major components unless requested by maintainers.
- Integrate with existing systems rather than bypassing or overriding them.
- `root/app/Core/` files must not be replaced; small improvements or new files are acceptable.
- The **Login Controller** may only receive incremental changes—its core functionality must remain intact.

2. UI & Design Consistency
- **SPECTRE.CSS** is the required CSS foundation for UI consistency.  
  Do not replace, remove, or override it.
- All UI must use SPECTRE.CSS classes and patterns.
- Do not add other CSS frameworks, resets, or global overrides.

3. Development Discipline
- Contributions should emphasize maintainability, extensibility, and performance.
- Prioritize enhancements, bug fixes, and new features aligned with the roadmap.
- Avoid large rewrites or breaking changes.
- Follow framework coding standards, API design, and naming conventions.
- Keep documentation accurate and up to date.

4. Stability & Compatibility
- Do not add dependencies that increase complexity or alter deployment requirements.
- Ensure backward compatibility for all changes.
- Do not break existing APIs or remove key features without consensus.

5. Prohibited Architectural Changes
- Do not replace the MVC architecture or introduce alternative paradigms.
- Do not force specific ORMs, template engines, or libraries.

6. **Security & Reliability**
- Validate and sanitize all user input to prevent injection or XSS vulnerabilities.
- Follow the principle of least privilege when adding permissions or roles.
- Ensure new UI or features meet accessibility standards (contrast, keyboard navigation, ARIA where needed).
- Avoid changes that introduce performance regressions or inflate asset size.

---

## Workflow

1. **Implement Changes**
   - Make requested updates, fixes, or new features.
   - Keep commits small, focused, and reviewable.

2. **Write or Update Tests**
   - Add tests for all new features, bug fixes, and refactors.
   - Ensure edge cases are covered.
   - Place tests in the designated test directory.
   - Confirm tests are updated or created.

3. **Local Verification**
   - Run the full test suite—no errors allowed.  
   - Confirm all tests pass locally.
   - Lint and format the code.  
   - Fix all linting or formatting issues.  
   - Confirm linting passes with no errors.

4. **Documentation & Metadata**
   - Update `CHANGELOG.md` with relevant entries.  
   - Update `README.md` if usage or setup changes.  
   - Confirm both files are up to date.

5. **Prepare Commit**
   - Only commit once tests and linting pass locally.
   - Write clear, descriptive commit messages.  
   - Confirm commit messages are clear.

6. **Open Pull Request**
   - Ensure the branch passes all automated checks (tests, linting, builds).
   - Provide a concise summary of the changes and their purpose.  
   - Confirm PR description explains “why” and “what.”
   - Reference related issues or tickets when applicable.
   - Include screenshots for any UI changes.  
   - Confirm screenshots are provided when relevant.

---

## Notes
These are baseline expectations. Additional project-specific rules may apply—follow the strictest set of requirements.
