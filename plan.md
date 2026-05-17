# Chore Charts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a small browser-based chore chart generator that creates printable portrait or landscape responsibility charts with daily icon grids, full-width text chores, paid-chore markers, blank write-in lines, and a bottom section for weekly chores.

**Architecture:** Start with a static HTML app using Alpine.js for state and Tailwind CSS for styling. Keep all chart data in one Alpine component property persisted through the Alpine Persist plugin, render one print-first chart preview from that data, and add import/export so the chart can be backed up or moved between browsers.

**Tech Stack:** Static HTML, Alpine.js, Alpine Persist plugin using `$persist`, Tailwind CSS CLI build only, JSON import/export, print CSS.

---

## Product Shape

The app should generate a printable weekly chore chart similar to the screenshot:

- A large centered title, for example `Jack's Responsibility Chart`.
- A 7-day week layout: `Sun`, `Mon`, `Tue`, `Wed`, `Thu`, `Fri`, `Sat`.
- Each day has 3 narrow icon columns for quick visual chores.
- Each day also supports full-width rows that span all 3 icon columns for text chores.
- Any chore can be marked as paid so it prints with a small visible paid marker.
- Paid chores are marker-only; the app does not calculate or display paid chore counts, totals, or dollar amounts.
- The app can store separate charts for multiple children in the same persisted state.
- Sections divide the day, with default sections:
  - `Morning`
  - `Daytime`
  - `Before Bed`
- Each section can have:
  - Icon rows: 3 chore slots per day.
  - Text rows: one full-width chore label per day.
  - Blank write-in rows: printable ruled lines for writing chores by pen.
- A bottom weekly chores section spans the full page width for chores that only need to happen once during the week.
- Days use light background colors to make the week easy to scan.
- The final output should be optimized for printing, not for a marketing-style web page.

## Initial Defaults

Use these defaults on first load:

- Child name: `Jack`
- Orientation: `landscape`
- Days: Sunday through Saturday, always Sunday first.
- Sections:
  - `Morning`
  - `Daytime`
  - `Before Bed`
- Day colors:
  - Sunday: light lavender
  - Monday: light blue
  - Tuesday: light peach
  - Wednesday: light pink
  - Thursday: light blue-gray
  - Friday: light green
  - Saturday: light yellow
- Icon row count per section: 2
- Blank write-in line count per section: 2
- Weekly chores section title: `Weekly Chores`
- Weekly chores default rows:
  - 3 blank write-in rows
  - 2 example text rows, such as `Clean bedroom` and `Put away laundry`
- Paid chore marker: a small coin icon badge, visible in color and grayscale print.
- Paid chore totals/counts: none.
- Icon options:
  - Built-in custom SVG line icons defined in the app, such as `bed`, `toothbrush`, `laundry`, `dishes`, `trash`, `backpack`, `room`, and `coin`.
  - Icons are black, outline-only, and color-free.
  - No emoji icons, uploaded images, or uploaded SVG files in version 1.

## Data Model

Persist one JSON object with Alpine Persist under the versioned key `chore-chart:v1`:

```json
{
  "version": 1,
  "activeChildId": "jack",
  "children": [
    {
      "id": "jack",
      "childName": "Jack",
      "orientation": "landscape",
      "days": [
        { "key": "sun", "label": "Sun", "color": "#ded8ef" },
        { "key": "mon", "label": "Mon", "color": "#cfe0f8" },
        { "key": "tue", "label": "Tue", "color": "#fde6ca" },
        { "key": "wed", "label": "Wed", "color": "#f5c9cd" },
        { "key": "thu", "label": "Thu", "color": "#d2e2e6" },
        { "key": "fri", "label": "Fri", "color": "#dcefd7" },
        { "key": "sat", "label": "Sat", "color": "#fff2c7" }
      ],
      "sections": [
        {
          "id": "morning",
          "name": "Morning",
          "rows": [
            {
              "id": "morning-icons-1",
              "type": "icons",
              "cells": {
                "sun": [
                  { "iconType": "svg", "icon": "laundry", "paid": false },
                  { "iconType": "svg", "icon": "bed", "paid": false },
                  { "iconType": "svg", "icon": "toothbrush", "paid": false }
                ],
                "mon": [
                  { "iconType": "svg", "icon": "laundry", "paid": false },
                  { "iconType": "svg", "icon": "bed", "paid": false },
                  { "iconType": "svg", "icon": "toothbrush", "paid": false }
                ],
                "tue": [
                  { "iconType": "svg", "icon": "laundry", "paid": false },
                  { "iconType": "svg", "icon": "bed", "paid": false },
                  { "iconType": "svg", "icon": "toothbrush", "paid": false }
                ],
                "wed": [
                  { "iconType": "svg", "icon": "laundry", "paid": false },
                  { "iconType": "svg", "icon": "bed", "paid": false },
                  { "iconType": "svg", "icon": "toothbrush", "paid": false }
                ],
                "thu": [
                  { "iconType": "svg", "icon": "laundry", "paid": false },
                  { "iconType": "svg", "icon": "bed", "paid": false },
                  { "iconType": "svg", "icon": "toothbrush", "paid": false }
                ],
                "fri": [
                  { "iconType": "svg", "icon": "laundry", "paid": false },
                  { "iconType": "svg", "icon": "bed", "paid": false },
                  { "iconType": "svg", "icon": "toothbrush", "paid": false }
                ],
                "sat": [
                  { "iconType": "svg", "icon": "laundry", "paid": false },
                  { "iconType": "svg", "icon": "bed", "paid": false },
                  { "iconType": "svg", "icon": "toothbrush", "paid": false }
                ]
              }
            },
            {
              "id": "morning-text-1",
              "type": "text",
              "cells": {
                "sun": { "label": "Feed cat", "paid": false },
                "mon": { "label": "Feed cat", "paid": false },
                "tue": { "label": "Feed cat", "paid": false },
                "wed": { "label": "Feed cat", "paid": false },
                "thu": { "label": "Feed cat", "paid": false },
                "fri": { "label": "Feed cat", "paid": false },
                "sat": { "label": "Feed cat", "paid": false }
              }
            },
            {
              "id": "morning-write-1",
              "type": "write-in",
              "paid": false,
              "cells": {
                "sun": "",
                "mon": "",
                "tue": "",
                "wed": "",
                "thu": "",
                "fri": "",
                "sat": ""
              }
            }
          ]
        }
      ],
      "weeklyChores": {
        "title": "Weekly Chores",
        "rows": [
          { "id": "weekly-1", "type": "text", "label": "Clean bedroom", "paid": true },
          { "id": "weekly-2", "type": "text", "label": "Put away laundry", "paid": false },
          { "id": "weekly-write-1", "type": "write-in", "label": "", "paid": false },
          { "id": "weekly-write-2", "type": "write-in", "label": "", "paid": false },
          { "id": "weekly-write-3", "type": "write-in", "label": "", "paid": false }
        ]
      }
    }
  ]
}
```

Rules:

- The root state stores `activeChildId` and a `children` array.
- Each child stores its own `childName`, orientation, Sunday-first days, sections, and weekly chores.
- The editor and preview operate on the active child chart.
- Print output can print the selected child chart or all child charts in the same saved state.
- Child IDs are stable slugs generated when a child chart is created.
- The days array always has exactly 7 entries in this order: `sun`, `mon`, `tue`, `wed`, `thu`, `fri`, `sat`.
- `icons` rows render 3 subcells per day.
- `text` rows render one full-width cell per day spanning the 3 subcolumns.
- `write-in` rows render one full-width ruled line per day spanning the 3 subcolumns.
- Icon chores use `{ "iconType": "svg", "icon": "bed", "paid": false }` so each of the 3 daily icon slots can be marked independently.
- For `iconType: "svg"`, `icon` stores a key from the built-in custom SVG line-icon registry.
- Imported JSON should reject icon entries whose `iconType` is not `svg` or whose `icon` value is not in the built-in custom SVG registry.
- Uploaded image files, uploaded SVG files, and arbitrary SVG markup are not supported.
- Daily text chores use `{ "label": "...", "paid": false }` so a paid marker can be attached per day.
- Write-in rows have a row-level `paid` flag so the printed blank line can indicate that a handwritten chore is paid.
- `weeklyChores` renders after all daily sections and spans the full chart width.
- Weekly `text` rows render as full-width rows with readable labels.
- Weekly `write-in` rows render as full-width ruled lines for pen-written chores.
- Weekly rows have a row-level `paid` flag.
- Keep row IDs stable so future drag/reorder behavior can be added without changing saved data.
- Import should reject JSON without `version`, `activeChildId`, and a non-empty `children` array.
- Import should reject child charts without Sunday-first `days`, `sections`, or `weeklyChores`.

## Screen Layout

The app has two main regions:

- Editor panel: controls for data entry and settings.
- Preview panel: the printable chart exactly as it will appear on paper.

For desktop:

- Put the editor in a left sidebar or top toolbar.
- Keep the print preview large and scrollable.

For mobile:

- Stack editor controls above the preview.
- The preview may horizontally scroll; preserving print proportions is more important than fitting every column into a phone viewport.

## Editor Requirements

Chart settings:

- Child selector for choosing the active child chart.
- Child name input.
- Add child chart.
- Duplicate active child chart.
- Delete active child chart, with confirmation and a guard that at least one child chart remains.
- Orientation segmented control: `Portrait` / `Landscape`.
- Paper size select: start with `Letter`; optionally support `A4`.
- Button: `Print`.
- Button: `Export JSON`.
- Button/input: `Import JSON`.
- Button: `Reset to defaults`.

Section controls:

- Add section.
- Rename section.
- Delete section, with confirmation.
- Move section up/down.

Row controls inside each section:

- Add icon row.
- Add text row.
- Add blank write-in row.
- Delete row.
- Move row up/down.

Cell editing:

- For icon rows, allow editing the 3 icon slots for each day using an icon picker that includes only built-in custom SVG line-icon options.
- For icon rows, add a paid toggle for each SVG icon slot.
- For text rows, allow editing one text value per day.
- For text rows, add a paid toggle for each day.
- For write-in rows, no text is required; optionally allow a faint placeholder label that does not print.
- For write-in rows, add a row-level paid toggle so the blank line can print with a paid marker.

Day controls:

- Edit day labels.
- Edit day background colors.
- Preserve exactly 7 day columns in Sunday-first order for version 1.
- Do not provide a Monday-first or custom week-start control.

Weekly chore controls:

- Rename the weekly chores section.
- Add weekly text chore.
- Add weekly blank write-in line.
- Edit weekly text chore labels.
- Toggle paid status for each weekly chore row.
- Delete weekly chore rows.
- Move weekly chore rows up/down.

## Print Layout Requirements

The printable chart should be built as a CSS grid:

- Top-level chart grid has 21 equal subcolumns: 7 days × 3 icon columns.
- Each day header spans 3 subcolumns.
- Each section name renders visibly as a section header row spanning all 21 subcolumns.
- An icon row creates 21 cells.
- A text row creates 7 cells, each spanning 3 subcolumns.
- A write-in row creates 7 cells, each spanning 3 subcolumns.
- The weekly chores section appears below the daily grid and spans all 21 subcolumns.
- Weekly rows should use a two-column-friendly layout on wide landscape pages when there are many rows, but the first version can use full-width rows for maximum handwriting space.
- Paid chores render a compact coin icon marker in the cell.
- Paid markers must not resize the grid cell or overlap the icon, chore text, or ruled write-in line.
- Do not render a paid chore count, subtotal, total, dollar amount, or payment summary anywhere on the chart.

Sizing targets for landscape letter:

- Page size: `11in × 8.5in`.
- Print margins: about `0.25in`.
- Title height: about `0.45in`.
- Day header height: about `0.35in`.
- Icon cell height: about `0.42in` to `0.55in`.
- Text/write-in row height: about `0.38in` to `0.5in`.
- Weekly row height: about `0.35in` to `0.45in`.
- Grid borders: thin light gray lines, visible after printing.

Sizing targets for portrait letter:

- Page size: `8.5in × 11in`.
- Keep 7 days across the page.
- Reduce icon size and row height as needed.
- Avoid clipping content at page edges.

Print behavior:

- Hide the editor when printing.
- Print only the chart preview.
- Use `@page` to set size and orientation:

```css
@page {
  size: letter landscape;
  margin: 0.25in;
}
```

- Switch to `letter portrait` when portrait orientation is selected.
- Use `break-inside: avoid` on section blocks where possible.
- Avoid large shadows, rounded cards, or decorations that waste ink.

## Visual Design

Follow the screenshot as the visual reference:

- Big centered title with the child name.
- Bold day labels.
- Visible section-name rows with restrained type, enough contrast to separate `Morning`, `Daytime`, and `Before Bed` without wasting print space.
- Light pastel day backgrounds.
- Thin gray grid lines.
- Large centered black SVG line icons in icon cells.
- Custom SVG icons should be simple, high-contrast, black, outline-only printable inline SVGs controlled by the app, not user-uploaded markup.
- Full-width text rows should be easy to read but not oversized.
- Blank write-in rows should have a subtle ruled line across each day cell.
- Weekly chores should feel like part of the printed chart, using the same border weight and restrained typography.
- Paid markers should use the built-in `coin` SVG icon and remain readable when printed in grayscale; do not rely on color alone.
- Keep cards and decorative UI outside the print area.

CSS notes:

- Use fixed print dimensions for the chart preview.
- Use responsive scaling only for on-screen preview, not print output.
- Do not use viewport-based font sizing.
- Keep letter spacing at `0`.

## File Plan

Start with a small static app:

- Create `index.html`
  - Loads Alpine.js, Alpine Persist, and Tailwind.
  - Contains the editor UI and chart preview.
  - Owns the first-pass Alpine component.
- Create `assets/app.css`
  - Print CSS.
  - Chart grid CSS.
  - Screen-only preview scaling.
- Create `assets/app.js`
  - Default chart factory.
  - Alpine component state and actions.
  - Alpine Persist `$persist(defaultChart()).as('chore-chart:v1')` setup.
  - JSON import/export helpers.
- Create `assets/icons.js`
  - Built-in custom SVG icon registry.
  - Icon picker options for built-in SVG line icons.
  - Helper functions for resolving an icon object into inline SVG markup.
  - Built-in `coin` SVG used for paid chore markers.

The first version can keep all state logic in `assets/app.js`. Split into smaller modules only after the behavior is working and the file becomes hard to navigate.

## Implementation Tasks

### Task 1: Static App Shell

- [ ] Create `index.html` with Tailwind, Alpine.js, Alpine Persist, `assets/app.css`, and `assets/app.js`.
- [ ] Load `assets/icons.js` before `assets/app.js`.
- [ ] Add a root Alpine component named `choreChartApp`.
- [ ] Add screen regions for editor and preview.
- [ ] Confirm the page opens locally with no build step.

### Task 2: Default Chart State

- [ ] Create `defaultChart()` in `assets/app.js`.
- [ ] Create `defaultChildChart(childName)` for adding additional children with the same default chart structure.
- [ ] Use icon objects with `iconType`, `icon`, and `paid` for every icon slot.
- [ ] Store chart state with Alpine Persist using `$persist(defaultChart()).as('chore-chart:v1')`.
- [ ] Add `resetToDefaults()` with a confirmation prompt.
- [ ] Resolve the active child from `activeChildId`.
- [ ] Render title, day headers, visible section-name rows, icon rows, text rows, write-in rows, and weekly chores from the active child chart.
- [ ] Render SVG icon slots from the built-in SVG registry.
- [ ] Render coin paid markers for paid icon chores, paid text chores, paid write-in rows, and paid weekly chores.

### Task 3: Print Grid

- [ ] Implement the 21-column print grid.
- [ ] Make day headers span 3 columns.
- [ ] Make visible section headers span all 21 columns.
- [ ] Make icon rows render 3 cells per day.
- [ ] Make text rows and write-in rows span all 3 subcolumns per day.
- [ ] Make the weekly chores section span all 21 subcolumns below the daily sections.
- [ ] Add pastel day background colors to every cell.
- [ ] Add borders that match the screenshot.
- [ ] Position coin paid markers without changing row height or column width.

### Task 4: Editor Controls

- [ ] Add chart setting controls for active child selection, child name, paper size, and orientation.
- [ ] Add child chart controls for creating, duplicating, deleting, and switching child charts.
- [ ] Add section create, rename, delete, and move controls.
- [ ] Add row create, delete, and move controls.
- [ ] Add cell editors for icon and text rows.
- [ ] Add an icon picker that supports built-in custom SVG line-icon choices.
- [ ] Add paid toggles for icon slots, daily text chores, daily write-in rows, and weekly rows.
- [ ] Add day label and color editors.
- [ ] Keep the week order fixed as Sunday through Saturday.
- [ ] Add weekly chore section controls for renaming, adding, editing, deleting, and moving weekly rows.

### Task 5: Import, Export, and Print

- [ ] Add JSON export by downloading the current chart as a `.json` file.
- [ ] Add JSON import from a file input.
- [ ] Validate imported JSON before replacing current state.
- [ ] Validate that imported state has `activeChildId` pointing to an existing child chart.
- [ ] Validate that every imported child chart uses Sunday-first day order.
- [ ] Reject imported icon entries with unknown `iconType` values or unknown built-in SVG keys.
- [ ] Add a print button that calls `window.print()`.
- [ ] Hide editor controls in print media.

### Task 6: Print Polish

- [ ] Verify landscape letter print preview fits on one page with default data.
- [ ] Verify portrait letter print preview does not clip.
- [ ] Tune row heights so there is room for 3 icon columns per day plus blank write-in rows.
- [ ] Tune the weekly chores section so it remains visible at the bottom of the default one-page landscape print.
- [ ] Make sure long text chores wrap within the day cell without overlapping adjacent cells.
- [ ] Make sure visible section headers do not crowd the daily rows or weekly chores.
- [ ] Make sure coin paid markers remain visible without overlapping text, icons, or write-in lines.
- [ ] Confirm the print view still works when a section has only write-in rows.
- [ ] Confirm the print view still works when the weekly chores section has only blank write-in rows.

## Acceptance Criteria

- The app opens from `index.html` without a build process.
- Editing the chart updates the preview immediately.
- Reloading the browser preserves edits through Alpine Persist.
- Exported JSON can be imported into a fresh browser session and recreate the same chart.
- The same persisted state can contain charts for multiple children.
- The editor can switch between child charts and print the selected child chart.
- Week columns are always Sunday first.
- The printed landscape chart resembles the screenshot:
  - 7 day columns.
  - 3 icon subcolumns per day.
  - pastel day backgrounds.
  - bold day headers.
  - visible section names.
  - blank write-in lines.
- The bottom of the chart includes a weekly chores section that spans the full page width.
- Print mode hides the editor and prints only the chart.
- Default landscape letter output fits on one page.
- Text rows and write-in rows span each full day width, not just one icon subcolumn.
- Weekly text rows and weekly write-in rows remain readable and writable after printing.
- Paid chores are visibly marked with the built-in coin icon in the preview and in print.
- Coin paid markers work for daily icon chores, daily text chores, daily write-in rows, weekly text chores, and weekly write-in rows.
- The chart only marks which chores are paid; it does not show paid chore counts, totals, or dollar amounts.
- Icon cells support built-in custom SVG line icons only.
- The app does not support emoji icons, uploaded images, uploaded SVG files, or arbitrary SVG markup.

## Out of Scope for Version 1

- User accounts.
- Server-side storage.
- Drag-and-drop reordering.
- Emoji icons, uploaded image icons, uploaded SVG files, and arbitrary user-provided SVG markup.
- Paid chore counts, totals, dollar amounts, and payment summaries.
- Printing multiple children on one page.
- Recurring schedule rules.
- Mobile-perfect editing experience.
