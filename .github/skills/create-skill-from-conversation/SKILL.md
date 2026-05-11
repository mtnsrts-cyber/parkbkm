---
name: create-skill-from-conversation
description: 'Create a reusable SKILL.md from conversation patterns. Use when a workflow appears in chat and you want a repeatable, quality-checked skill with clear decision points and completion criteria.'
argument-hint: 'What outcome should the skill produce?'
user-invocable: true
disable-model-invocation: false
---

# Create Skill From Conversation

## What This Produces
A complete SKILL.md file that turns a conversation workflow into a reusable procedure.

## When to Use
- You followed a repeated multi-step process in chat.
- You want a slash-invocable workflow for similar tasks.
- You need explicit decision branches and quality checks.

## Inputs To Collect
- Target outcome the skill should produce
- Scope: workspace or personal
- Depth: quick checklist or full workflow

## Procedure
1. Extract workflow from chat history.
2. Capture step sequence as action verbs.
3. Identify decision points.
Decision points to capture:
- If no clear workflow exists, ask user for outcome, scope, and depth.
- If workflow is too broad, split into phases and keep SKILL.md focused.
4. Define quality criteria.
Quality checks to include:
- File location and naming are valid.
- Frontmatter is syntactically correct.
- Description contains discovery keywords.
- Procedure is testable step-by-step.
5. Draft SKILL.md with frontmatter and body sections.
6. Save to correct path.
Project paths:
- .github/skills/<skill-name>/SKILL.md
- .agents/skills/<skill-name>/SKILL.md
- .claude/skills/<skill-name>/SKILL.md
Personal paths:
- ~/.copilot/skills/<skill-name>/SKILL.md
- ~/.agents/skills/<skill-name>/SKILL.md
- ~/.claude/skills/<skill-name>/SKILL.md
7. Review for ambiguity.
Ambiguity checks:
- Are triggers concrete enough for discovery?
- Are branch conditions explicit?
- Are completion checks measurable?
8. Ask targeted follow-up questions on weak points.
9. Revise and finalize.

## Completion Criteria
- SKILL.md exists in a valid skill folder.
- skill name matches folder name.
- Description states what and when to use.
- Procedure includes branches and quality checks.
- User can invoke it with a clear prompt.

## Example Prompts
- Create a skill that standardizes bug triage for Yii controller errors.
- Turn our code review approach into a reusable SKILL.md.
- Build a skill for migration safety checks before deploy.
