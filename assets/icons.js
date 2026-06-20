(function () {
  const uiIconMap = {
    coin: "coin",
    grip: "grip-vertical",
    arrowUp: "arrow-up",
    arrowDown: "arrow-down",
    plus: "plus",
    delete: "trash",
    close: "x",
    eye: "eye",
    eyeOff: "eye-off",
    help: "help-circle",
    info: "info-circle",
    bulb: "bulb"
  };

  const suggestedTablerIcons = [
    "bed",
    "wash-machine",
    "trash",
    "backpack",
    "vacuum-cleaner",
    "cat",
    "dog",
    "home",
    "tools-kitchen-2",
    "brush",
    "shirt",
    "hanger",
    "paper-bag",
    "school",
    "books",
    "bucket",
    "mop",
    "plant",
    "apple",
    "milk",
    "carrot",
    "toilet-paper",
    "bath",
    "bed-flat"
  ];

  function tablerIcons() {
    return window.TablerIconRegistry ? window.TablerIconRegistry.icons : {};
  }

  function tablerOptions() {
    return window.TablerIconRegistry ? window.TablerIconRegistry.options : [];
  }

  function iconExists(icon) {
    return Boolean(tablerIcons()[icon]);
  }

  function renderTablerIcon(name) {
    return tablerIcons()[name] || "";
  }

  function renderIcon(icon) {
    if (!icon) return "";
    return renderTablerIcon(icon.icon);
  }

  function iconValue(option) {
    return option.icon;
  }

  function suggestedOptions() {
    const tablerByName = new Map(tablerOptions().map((option) => [option.icon, option]));
    return suggestedTablerIcons.map((name) => tablerByName.get(name)).filter(Boolean);
  }

  function uiIcon(name) {
    return renderTablerIcon(uiIconMap[name] || name);
  }

  window.ChoreChartIcons = {
    uiIconMap,
    suggestedTablerIcons,
    get tablerIcons() {
      return tablerIcons();
    },
    get tablerOptions() {
      return tablerOptions();
    },
    get iconOptions() {
      return suggestedOptions();
    },
    renderIcon,
    uiIcon,
    iconValue,
    iconExists
  };
})();
