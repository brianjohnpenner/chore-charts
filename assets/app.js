(function () {
  const DB_NAME = "chore-chart-db";
  const DB_VERSION = 1;
  const STORE_NAME = "app-state";
  const CHART_KEY = "active-chart";
  const FILE_HANDLE_KEY = "json-file-handle";
  const CHART_VERSION = 2;
  const DAY_KEYS = ["sun", "mon", "tue", "wed", "thu", "fri", "sat"];
  const DAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
  const DAY_COLORS = ["#ded8ef", "#cfe0f8", "#fde6ca", "#f5c9cd", "#d2e2e6", "#dcefd7", "#fff2c7"];
  const ROW_TYPES = ["icon", "regular", "empty"];

  function openChartDatabase() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          db.createObjectStore(STORE_NAME);
        }
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  function readStoredValue(key) {
    return openChartDatabase().then((db) => new Promise((resolve, reject) => {
      const transaction = db.transaction(STORE_NAME, "readonly");
      const request = transaction.objectStore(STORE_NAME).get(key);
      request.onsuccess = () => resolve(request.result || null);
      request.onerror = () => reject(request.error);
      transaction.oncomplete = () => db.close();
      transaction.onerror = () => {
        db.close();
        reject(transaction.error);
      };
    }));
  }

  function writeStoredValue(key, value) {
    return openChartDatabase().then((db) => new Promise((resolve, reject) => {
      const transaction = db.transaction(STORE_NAME, "readwrite");
      const request = transaction.objectStore(STORE_NAME).put(value, key);
      request.onerror = () => reject(request.error);
      transaction.oncomplete = () => {
        db.close();
        resolve();
      };
      transaction.onerror = () => {
        db.close();
        reject(transaction.error);
      };
    }));
  }

  async function loadStoredChart() {
    try {
      const stored = await readStoredValue(CHART_KEY);
      if (stored) return normalizeChart(stored);

      const chart = defaultChart();
      await saveStoredChart(chart);
      return chart;
    } catch (error) {
      console.error("Could not load chart data from IndexedDB.", error);
      return defaultChart();
    }
  }

  async function saveStoredChart(chart) {
    await writeStoredValue(CHART_KEY, normalizeChart(clone(chart)));
  }

  async function loadFileHandle() {
    try {
      return await readStoredValue(FILE_HANDLE_KEY);
    } catch (error) {
      console.error("Could not load JSON file handle.", error);
      return null;
    }
  }

  async function saveFileHandle(handle) {
    try {
      await writeStoredValue(FILE_HANDLE_KEY, handle);
    } catch (error) {
      console.error("Could not remember JSON file handle.", error);
    }
  }

  function debounce(callback, delay = 400) {
    let timeout;
    return (...args) => {
      window.clearTimeout(timeout);
      timeout = window.setTimeout(() => callback(...args), delay);
    };
  }

  function uid(prefix) {
    return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
  }

  function slugify(value) {
    const slug = String(value || "child")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
    return slug || "child";
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function chartJson(chart) {
    return JSON.stringify(normalizeChart(clone(chart)), null, 2);
  }

  function filePickerTypes() {
    return [{
      description: "Chore chart JSON",
      accept: { "application/json": [".json"] }
    }];
  }

  async function verifyFilePermission(handle, mode = "readwrite") {
    if (!handle || !handle.queryPermission || !handle.requestPermission) return false;
    if (await handle.queryPermission({ mode }) === "granted") return true;
    return await handle.requestPermission({ mode }) === "granted";
  }

  async function writeJsonFile(handle, chart) {
    const writable = await handle.createWritable();
    await writable.write(new Blob([chartJson(chart)], { type: "application/json" }));
    await writable.close();
  }

  function days() {
    return DAY_KEYS.map((key, index) => ({
      key,
      label: DAY_LABELS[index],
      color: DAY_COLORS[index]
    }));
  }

  function daySelection(defaultValue = true) {
    return Object.fromEntries(DAY_KEYS.map((key) => [key, defaultValue]));
  }

  function choreRow(type, label = "", icon = "laundry", paid = false, selectedDays = daySelection(true)) {
    return {
      id: uid(type),
      type,
      label,
      iconType: "svg",
      icon,
      paid,
      days: selectedDays
    };
  }

  function weeklyRow(type, label = "", paid = false) {
    return {
      id: uid(`weekly-${type}`),
      type,
      label,
      paid
    };
  }

  function defaultSection(id, name) {
    const rows = [
      choreRow("icon", "Laundry", "laundry"),
      choreRow("icon", "Make bed", "bed"),
      choreRow("icon", "Brush teeth", "toothbrush")
    ];

    if (name === "Morning") {
      rows.push(choreRow("regular", "Feed cat", "room"));
    }

    return { id, name, rows };
  }

  function defaultWeeklyChores() {
    return {
      title: "Weekly Chores",
      rows: [
        weeklyRow("regular", "Clean bedroom", true),
        weeklyRow("regular", "Put away laundry", false),
        weeklyRow("empty"),
        weeklyRow("empty"),
        weeklyRow("empty")
      ]
    };
  }

  function defaultChildChart(childName = "Jack", existingIds = []) {
    let id = slugify(childName);
    if (existingIds.includes(id)) {
      let i = 2;
      while (existingIds.includes(`${id}-${i}`)) i += 1;
      id = `${id}-${i}`;
    }

    return {
      id,
      childName,
      orientation: "landscape",
      paperSize: "letter",
      days: days(),
      sections: [
        defaultSection("morning", "Morning"),
        defaultSection("daytime", "Daytime"),
        defaultSection("before-bed", "Before Bed")
      ],
      weeklyChores: defaultWeeklyChores()
    };
  }

  function defaultChart() {
    const child = defaultChildChart("Jack");
    return {
      version: CHART_VERSION,
      activeChildId: child.id,
      children: [child]
    };
  }

  function validDays(candidate) {
    return Array.isArray(candidate) &&
      candidate.length === DAY_KEYS.length &&
      candidate.every((day, index) => day && day.key === DAY_KEYS[index]);
  }

  function normalizeDays(selection, defaultValue = true) {
    const source = selection && typeof selection === "object" ? selection : {};
    return Object.fromEntries(DAY_KEYS.map((key) => [key, source[key] !== undefined ? Boolean(source[key]) : defaultValue]));
  }

  function validIcon(icon) {
    return window.ChoreChartIcons && window.ChoreChartIcons.svgIcons[icon] ? icon : "room";
  }

  function normalizeChoreRow(row) {
    const type = ROW_TYPES.includes(row && row.type) ? row.type : "regular";
    return {
      id: row.id || uid(type),
      type,
      label: type === "empty" ? "" : String(row.label || ""),
      iconType: "svg",
      icon: validIcon(row.icon || "room"),
      paid: Boolean(row.paid),
      days: normalizeDays(row.days, true)
    };
  }

  function normalizeWeeklyRow(row) {
    const type = row && row.type === "empty" ? "empty" : "regular";
    return {
      id: row.id || uid(`weekly-${type}`),
      type,
      label: type === "empty" ? "" : String(row.label || ""),
      paid: Boolean(row.paid)
    };
  }

  function migrateLegacyIconRow(row) {
    const firstDay = row.cells && row.cells[DAY_KEYS[0]];
    if (!Array.isArray(firstDay)) return [];
    return firstDay.map((slot) => choreRow("icon", "", validIcon(slot && slot.icon), Boolean(slot && slot.paid)));
  }

  function migrateLegacyTextRow(row) {
    const firstValue = row.cells && row.cells[DAY_KEYS[0]];
    const label = firstValue && typeof firstValue === "object" ? firstValue.label : "";
    const paid = firstValue && typeof firstValue === "object" ? Boolean(firstValue.paid) : false;
    return label ? [choreRow("regular", label, "room", paid)] : [];
  }

  function migrateLegacySection(section) {
    const rows = [];
    if (Array.isArray(section.rows)) {
      section.rows.forEach((row) => {
        if (row.type === "icons") rows.push(...migrateLegacyIconRow(row));
        if (row.type === "text") rows.push(...migrateLegacyTextRow(row));
        if (row.type === "write-in") rows.push(choreRow("empty", "", "room", Boolean(row.paid)));
      });
    }
    return {
      id: section.id || uid("section"),
      name: section.name || "Section",
      rows
    };
  }

  function migrateLegacyWeekly(weekly) {
    return {
      title: weekly && weekly.title ? weekly.title : "Weekly Chores",
      rows: Array.isArray(weekly && weekly.rows)
        ? weekly.rows.map((row) => normalizeWeeklyRow({
            id: row.id,
            type: row.type === "write-in" ? "empty" : "regular",
            label: row.label,
            paid: row.paid
          }))
        : defaultWeeklyChores().rows
    };
  }

  function migrateLegacyChart(chart) {
    const children = chart.children.map((child) => ({
      id: child.id || slugify(child.childName),
      childName: child.childName || "Child",
      orientation: child.orientation || "landscape",
      paperSize: child.paperSize || "letter",
      days: validDays(child.days) ? child.days : days(),
      sections: Array.isArray(child.sections) ? child.sections.map(migrateLegacySection) : [],
      weeklyChores: migrateLegacyWeekly(child.weeklyChores)
    }));

    return {
      version: CHART_VERSION,
      activeChildId: children.some((child) => child.id === chart.activeChildId) ? chart.activeChildId : children[0].id,
      children
    };
  }

  function normalizeChart(chart) {
    if (!chart || !Array.isArray(chart.children) || chart.children.length === 0) return defaultChart();
    if (chart.version === 1) return normalizeChart(migrateLegacyChart(chart));
    if (chart.version !== CHART_VERSION) return defaultChart();

    chart.children.forEach((child) => {
      child.id = child.id || slugify(child.childName);
      child.childName = child.childName || "Child";
      child.paperSize = child.paperSize || "letter";
      child.orientation = child.orientation || "landscape";
      child.days = validDays(child.days) ? child.days : days();
      child.sections = Array.isArray(child.sections) ? child.sections : [];
      child.sections.forEach((section) => {
        section.id = section.id || uid("section");
        section.name = section.name || "Section";
        section.rows = Array.isArray(section.rows) ? section.rows.map(normalizeChoreRow) : [];
      });
      child.weeklyChores = child.weeklyChores || defaultWeeklyChores();
      child.weeklyChores.title = child.weeklyChores.title || "Weekly Chores";
      child.weeklyChores.rows = Array.isArray(child.weeklyChores.rows)
        ? child.weeklyChores.rows.map(normalizeWeeklyRow)
        : defaultWeeklyChores().rows;
    });

    if (!chart.children.some((child) => child.id === chart.activeChildId)) {
      chart.activeChildId = chart.children[0].id;
    }

    return chart;
  }

  function validateChart(chart) {
    if (!chart || chart.version !== CHART_VERSION) return `Imported file must be version ${CHART_VERSION}.`;
    if (!Array.isArray(chart.children) || chart.children.length === 0) return "Imported file must include at least one child chart.";
    if (!chart.children.some((child) => child.id === chart.activeChildId)) return "Active child must point to an existing child chart.";

    for (const child of chart.children) {
      if (!child.id || !child.childName) return "Every child chart needs an id and childName.";
      if (!validDays(child.days)) return `${child.childName} must use Sunday-first days.`;
      if (!Array.isArray(child.sections)) return `${child.childName} must include sections.`;
      if (!child.weeklyChores || !Array.isArray(child.weeklyChores.rows)) return `${child.childName} must include weekly chores.`;

      for (const section of child.sections) {
        if (!Array.isArray(section.rows)) return `${section.name || "A section"} has invalid rows.`;
        for (const row of section.rows) {
          if (!ROW_TYPES.includes(row.type)) return "Daily rows must be icon, regular, or empty.";
          if (!row.days || DAY_KEYS.some((key) => typeof row.days[key] !== "boolean")) return "Every daily row needs Sunday-first day checkboxes.";
          if (row.type === "icon" && !window.ChoreChartIcons.svgIcons[row.icon]) return `Unknown SVG icon: ${row.icon}`;
        }
      }
    }

    return "";
  }

  function moveItem(list, index, direction) {
    const next = index + direction;
    if (next < 0 || next >= list.length) return;
    const [item] = list.splice(index, 1);
    list.splice(next, 0, item);
  }

  document.addEventListener("alpine:init", () => {
    Alpine.data("choreChartApp", () => ({
      chart: defaultChart(),
      fileHandle: null,
      importError: "",
      dataStatus: "",
      saveChartDebounced: null,
      storageReady: false,
      printMode: "selected",
      viewMode: new URLSearchParams(window.location.search).get("view") === "preview" ? "preview" : "edit",

      async init() {
        this.saveChartDebounced = debounce(async (chart) => {
          try {
            await saveStoredChart(chart);
            this.dataStatus = "Saved in this browser.";
          } catch (error) {
            console.error("Could not save chart data to IndexedDB.", error);
            this.importError = "Could not save changes in this browser.";
          }
        });

        this.chart = await loadStoredChart();
        this.fileHandle = await loadFileHandle();
        this.storageReady = true;

        this.$watch("chart", (chart) => {
          if (!this.storageReady) return;
          this.saveChartDebounced(chart);
        });
      },

      get activeChild() {
        return this.chart.children.find((child) => child.id === this.chart.activeChildId) || this.chart.children[0];
      },

      get iconOptions() {
        return window.ChoreChartIcons.iconOptions;
      },

      get rowTypes() {
        return ROW_TYPES;
      },

      get paidCoin() {
        return window.ChoreChartIcons.svgIcons.coin;
      },

      uiIcon(name) {
        return window.ChoreChartIcons.svgIcons[name] || "";
      },

      iconValue(row) {
        return `${row.iconType || "svg"}:${row.icon || "room"}`;
      },

      setRowIcon(row, value) {
        const icon = window.ChoreChartIcons.parseIconValue(value);
        if (icon.iconType !== "svg") return;
        row.iconType = icon.iconType;
        row.icon = validIcon(icon.icon);
      },

      normalizeRowForType(row) {
        if (!ROW_TYPES.includes(row.type)) row.type = "regular";
        row.days = normalizeDays(row.days, true);
        row.iconType = "svg";
        row.icon = validIcon(row.icon || "room");
        if (row.type === "empty") row.label = "";
      },

      renderIcon(row) {
        return window.ChoreChartIcons.renderIcon(row);
      },

      sectionIconGroups(section) {
        const icons = section.rows.filter((row) => row.type === "icon");
        const groups = [];
        for (let i = 0; i < icons.length; i += 3) {
          groups.push({ id: `${section.id}-icons-${i}`, rows: icons.slice(i, i + 3) });
        }
        return groups;
      },

      sectionDetailRows(section) {
        return section.rows.filter((row) => row.type !== "icon");
      },

      printIconCells(group, child = this.activeChild) {
        return child.days.flatMap((day) => [0, 1, 2].map((slotIndex) => {
          const row = group.rows[slotIndex];
          return {
            key: `${group.id}-${day.key}-${slotIndex}`,
            color: day.color,
            row,
            visible: Boolean(row && row.days[day.key])
          };
        }));
      },

      printDayCells(row, child = this.activeChild) {
        return child.days.map((day) => ({
          key: `${row.id}-${day.key}`,
          color: day.color,
          row,
          visible: Boolean(row.days[day.key])
        }));
      },

      addChild() {
        const name = window.prompt("Child name", "New Child");
        if (!name) return;
        const child = defaultChildChart(name, this.chart.children.map((item) => item.id));
        this.chart.children.push(child);
        this.chart.activeChildId = child.id;
      },

      duplicateChild() {
        const source = clone(this.activeChild);
        const base = `${source.childName} Copy`;
        source.id = slugify(base);
        let i = 2;
        const existing = this.chart.children.map((child) => child.id);
        while (existing.includes(source.id)) {
          source.id = `${slugify(base)}-${i}`;
          i += 1;
        }
        source.childName = base;
        this.chart.children.push(source);
        this.chart.activeChildId = source.id;
      },

      deleteChild() {
        if (this.chart.children.length === 1) return;
        if (!window.confirm(`Delete ${this.activeChild.childName}'s chart?`)) return;
        const index = this.chart.children.findIndex((child) => child.id === this.chart.activeChildId);
        this.chart.children.splice(index, 1);
        this.chart.activeChildId = this.chart.children[Math.max(0, index - 1)].id;
      },

      addSection() {
        const name = window.prompt("Section name", "New Section");
        if (!name) return;
        this.activeChild.sections.push({ id: uid(slugify(name)), name, rows: [] });
      },

      deleteSection(index) {
        if (!window.confirm("Delete this section?")) return;
        this.activeChild.sections.splice(index, 1);
      },

      moveSection(index, direction) {
        moveItem(this.activeChild.sections, index, direction);
      },

      sortItems(list, itemId, position) {
        const index = list.findIndex((item) => item.id === itemId);
        if (index < 0 || position < 0 || position >= list.length || index === position) return;
        const [item] = list.splice(index, 1);
        list.splice(position, 0, item);
      },

      sortSections(itemId, position) {
        this.sortItems(this.activeChild.sections, itemId, position);
      },

      sortRows(rows, itemId, position) {
        this.sortItems(rows, itemId, position);
      },

      addRow(section, type) {
        section.rows.push(choreRow(type, type === "regular" ? "New chore" : "", type === "icon" ? "room" : "room"));
      },

      deleteRow(section, index) {
        section.rows.splice(index, 1);
      },

      moveRow(rows, index, direction) {
        moveItem(rows, index, direction);
      },

      addWeeklyRow(type) {
        this.activeChild.weeklyChores.rows.push(weeklyRow(type, type === "regular" ? "New weekly chore" : ""));
      },

      deleteWeeklyRow(index) {
        this.activeChild.weeklyChores.rows.splice(index, 1);
      },

      moveWeeklyRow(index, direction) {
        moveItem(this.activeChild.weeklyChores.rows, index, direction);
      },

      normalizeWeeklyRow(row) {
        if (row.type !== "empty") row.type = "regular";
        if (row.type === "empty") row.label = "";
      },

      suggestedFileName() {
        return `${slugify(this.activeChild.childName)}-chore-chart.json`;
      },

      downloadJson() {
        const blob = new Blob([chartJson(this.chart)], { type: "application/json" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = this.suggestedFileName();
        link.click();
        URL.revokeObjectURL(url);
        this.dataStatus = "Downloaded JSON file.";
      },

      async saveJson() {
        this.importError = "";
        if (!this.fileHandle || !(await verifyFilePermission(this.fileHandle, "readwrite"))) {
          await this.saveJsonAs();
          return;
        }

        try {
          await writeJsonFile(this.fileHandle, this.chart);
          await saveStoredChart(this.chart);
          this.dataStatus = `Saved ${this.fileHandle.name || "JSON file"}.`;
        } catch (error) {
          console.error("Could not save JSON file.", error);
          this.importError = "Could not save JSON file.";
        }
      },

      async saveJsonAs() {
        this.importError = "";
        if (!window.showSaveFilePicker) {
          this.downloadJson();
          return;
        }

        try {
          const handle = await window.showSaveFilePicker({
            suggestedName: this.suggestedFileName(),
            types: filePickerTypes()
          });
          await writeJsonFile(handle, this.chart);
          this.fileHandle = handle;
          await saveFileHandle(handle);
          await saveStoredChart(this.chart);
          this.dataStatus = `Saved ${handle.name || "JSON file"}.`;
        } catch (error) {
          if (error && error.name === "AbortError") return;
          console.error("Could not save JSON file.", error);
          this.importError = "Could not save JSON file.";
        }
      },

      async openJsonFile() {
        this.importError = "";
        if (!window.showOpenFilePicker) {
          this.$refs.jsonFileInput.click();
          return;
        }

        try {
          const [handle] = await window.showOpenFilePicker({
            multiple: false,
            types: filePickerTypes()
          });
          const file = await handle.getFile();
          await this.applyImportedJson(await file.text());
          this.fileHandle = handle;
          await saveFileHandle(handle);
          this.dataStatus = `Opened ${handle.name || "JSON file"}.`;
        } catch (error) {
          if (error && error.name === "AbortError") return;
          console.error("Could not open JSON file.", error);
          this.importError = "Could not open JSON file.";
        }
      },

      async applyImportedJson(contents) {
        const parsed = normalizeChart(JSON.parse(contents));
        const error = validateChart(parsed);
        if (error) {
          this.importError = error;
          return false;
        }
        this.chart = parsed;
        await saveStoredChart(parsed);
        this.dataStatus = "Imported JSON file.";
        return true;
      },

      importJson(event) {
        const file = event.target.files[0];
        this.importError = "";
        if (!file) return;
        const reader = new FileReader();
        reader.onload = async () => {
          try {
            await this.applyImportedJson(reader.result);
          } catch (error) {
            this.importError = "Imported file is not valid JSON.";
          } finally {
            event.target.value = "";
          }
        };
        reader.readAsText(file);
      },

      resetToDefaults() {
        if (!window.confirm("Reset all saved chore charts?")) return;
        this.chart = defaultChart();
        saveStoredChart(this.chart)
          .then(() => {
            this.dataStatus = "Reset to default chart.";
          })
          .catch((error) => {
            console.error("Could not reset chart data in IndexedDB.", error);
            this.importError = "Could not reset saved chart data.";
          });
      },

      printChart(mode = "selected") {
        this.printMode = mode;
        window.setTimeout(() => window.print());
      }
    }));
  });

  window.ChoreChartDefaults = {
    defaultChart,
    defaultChildChart,
    validateChart,
    loadStoredChart,
    saveStoredChart
  };

  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("service-worker.js").catch((error) => {
        console.error("Could not register service worker.", error);
      });
    });
  }
})();
