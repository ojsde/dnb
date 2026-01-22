(function(vue) {
  "use strict";
  const _hoisted_1$1 = { class: "dnbSubmissionsTable" };
  const _hoisted_2$1 = {
    class: "dnb-button-container",
    style: { "display": "flex", "flex-wrap": "wrap", "gap": "0.5rem", "margin-bottom": "1rem" }
  };
  const _hoisted_3$1 = {
    key: 0,
    class: "dnb-spinner"
  };
  const _hoisted_4$1 = {
    key: 0,
    class: "dnb-spinner"
  };
  const _hoisted_5$1 = {
    key: 0,
    class: "dnb-spinner"
  };
  const _hoisted_6$1 = {
    key: 0,
    class: "dnb-spinner"
  };
  const _hoisted_7$1 = { style: { "margin-bottom": "0.5rem", "color": "#666", "font-size": "0.875rem" } };
  const _hoisted_8 = { style: { "display": "flex", "flex-direction": "column", "gap": "1rem", "width": "100%" } };
  const _hoisted_9 = { style: { "width": "100%", "max-width": "28rem" } };
  const _hoisted_10 = ["onKeydown"];
  const _hoisted_11 = {
    key: 0,
    style: { "display": "flex", "flex-wrap": "wrap", "gap": "0.5rem", "margin-top": "0.5rem" },
    role: "group",
    "aria-label": "Active search filters"
  };
  const _hoisted_12 = ["aria-label"];
  const _hoisted_13 = { class: "dnb-chip-label" };
  const _hoisted_14 = ["onClick", "aria-label"];
  const _hoisted_15 = {
    class: "dnb-filter-buttons",
    style: { "display": "flex", "flex-wrap": "wrap", "gap": "0.5rem" }
  };
  const _hoisted_16 = { style: { "margin": "0", "font-size": "1rem" } };
  const _hoisted_17 = {
    key: 0,
    style: { "margin": "0.5rem 0 0 0", "font-size": "0.875rem" }
  };
  const _hoisted_18 = ["value", "aria-label"];
  const _hoisted_19 = { class: "pkpBadge" };
  const _hoisted_20 = { class: "flex gap-2" };
  const _hoisted_21 = { class: "flex flex-col gap-1 flex-1" };
  const _hoisted_22 = { class: "dnb_authors" };
  const _hoisted_23 = ["href"];
  const _hoisted_24 = ["href"];
  const _hoisted_25 = {
    key: 1,
    class: "flex items-center gap-1 text-negative"
  };
  const _hoisted_26 = { class: "text-sm" };
  const _hoisted_27 = { key: 2 };
  const _hoisted_28 = { class: "text-sm text-negative" };
  const _hoisted_29 = ["title"];
  const _hoisted_30 = {
    key: 0,
    class: "pkpBadge dnb_deposited"
  };
  const _hoisted_31 = {
    key: 1,
    class: "pkpBadge dnb_queued"
  };
  const _hoisted_32 = {
    key: 2,
    class: "pkpBadge dnb_not_deposited"
  };
  const _hoisted_33 = {
    key: 3,
    class: "pkpBadge dnb_failed"
  };
  const MAX_SEARCH_LENGTH = 100;
  const MAX_FILTER_COUNT = 10;
  const _sfc_main$1 = {
    __name: "DNBSubmissionsTable",
    props: {
      data: { type: Object, required: true },
      actionUrls: { type: Object, required: true }
    },
    emits: ["set"],
    setup(__props, { emit: __emit }) {
      const { useLocalize } = pkp.modules.useLocalize;
      const { t } = useLocalize();
      const props = __props;
      const exportForm = vue.ref(null);
      const selectedSubmissions = vue.ref([]);
      const searchPhrase = vue.ref("");
      const activeSearchFilters = vue.ref([]);
      const activeStatusFilter = vue.ref(null);
      const filteredItems = vue.ref([]);
      const isSubmitting = vue.ref(false);
      let debounceTimeout = null;
      filteredItems.value = props.data.items;
      const areAllVisibleSelected = vue.computed(() => {
        if (filteredItems.value.length === 0) return false;
        const visibleIds = filteredItems.value.map((item) => item.id);
        return visibleIds.every((id) => selectedSubmissions.value.includes(id));
      });
      const itemCountText = vue.computed(() => {
        const filtered = filteredItems.value.length;
        const total = props.data.items.length;
        if (filtered === total) {
          const pluralMatch = props.data.i18n.itemCount.match(/\{[^}]+plural[^}]+one \{([^}]+)\} other \{([^}]+)\}\}/);
          if (pluralMatch) {
            const [, singular, plural] = pluralMatch;
            const word = total === 1 ? singular : plural;
            return props.data.i18n.itemCount.replace(/\{[^}]+plural[^}]+one \{[^}]+\} other \{[^}]+\}\}/, word).replace("{$count}", total);
          }
          return `${total} items`;
        }
        return props.data.i18n.itemCountFiltered.replace("{$filtered}", filtered).replace("{$total}", total);
      });
      const searchLabel = vue.computed(() => {
        return props.data.i18n.searchLabel || "Search";
      });
      const hasActiveFilters = vue.computed(() => {
        return activeStatusFilter.value !== null || activeSearchFilters.value.length > 0 || searchPhrase.value.trim() !== "";
      });
      const noResultsText = vue.computed(() => {
        if (hasActiveFilters.value) {
          return props.data.i18n.noResultsFiltered;
        }
        return props.data.i18n.noResults;
      });
      function setSearchPhrase(phrase) {
        let sanitized = phrase.trim();
        if (sanitized.length > MAX_SEARCH_LENGTH) {
          sanitized = sanitized.substring(0, MAX_SEARCH_LENGTH);
        }
        searchPhrase.value = sanitized;
        if (debounceTimeout) {
          clearTimeout(debounceTimeout);
        }
        debounceTimeout = setTimeout(() => {
          applyFilters();
        }, 300);
      }
      function addSearchFilter() {
        const phrase = searchPhrase.value.trim();
        if (!phrase) return;
        if (phrase.length > MAX_SEARCH_LENGTH) return;
        if (activeSearchFilters.value.includes(phrase)) return;
        if (activeSearchFilters.value.length >= MAX_FILTER_COUNT) {
          console.warn(`Maximum of ${MAX_FILTER_COUNT} search filters allowed`);
          return;
        }
        activeSearchFilters.value.push(phrase);
        searchPhrase.value = "";
        applyFilters();
      }
      function removeSearchFilter(index) {
        activeSearchFilters.value.splice(index, 1);
        applyFilters();
      }
      function setStatusFilter(status) {
        activeStatusFilter.value = status;
        applyFilters();
      }
      function applyFilters() {
        try {
          let items = props.data.items;
          if (activeStatusFilter.value !== null) {
            items = items.filter((item) => item.dnbStatusConst === activeStatusFilter.value);
          }
          const allSearchTerms = [...activeSearchFilters.value];
          if (searchPhrase.value.trim()) {
            allSearchTerms.push(searchPhrase.value.trim());
          }
          if (allSearchTerms.length > 0) {
            items = items.filter((item) => {
              return allSearchTerms.every((searchTerm) => {
                const lowerPhrase = searchTerm.toLowerCase();
                const authorsMatch = item.publication.authorsString?.toLowerCase().includes(lowerPhrase);
                const titleMatch = item.publication.fullTitle?.toLowerCase().includes(lowerPhrase);
                const idMatch = item.id.toString().includes(lowerPhrase);
                const issueMatch = item.issueTitle?.toLowerCase().includes(lowerPhrase);
                return authorsMatch || titleMatch || idMatch || issueMatch;
              });
            });
          }
          filteredItems.value = items;
        } catch (error) {
          console.error("Error applying filters:", error);
          filteredItems.value = props.data.items;
        }
      }
      function toggleSelectAll() {
        const visibleIds = filteredItems.value.map((item) => item.id);
        const allVisibleSelected = visibleIds.every((id) => selectedSubmissions.value.includes(id));
        if (allVisibleSelected && visibleIds.length > 0) {
          selectedSubmissions.value = selectedSubmissions.value.filter((id) => !visibleIds.includes(id));
        } else {
          const uniqueSelections = /* @__PURE__ */ new Set([...selectedSubmissions.value, ...visibleIds]);
          selectedSubmissions.value = Array.from(uniqueSelections);
        }
      }
      function deselectAll() {
        selectedSubmissions.value = [];
      }
      function handleAction(action) {
        if (!exportForm.value || isSubmitting.value) return;
        isSubmitting.value = true;
        exportForm.value.action = props.actionUrls[action];
        exportForm.value.submit();
        setTimeout(() => {
          isSubmitting.value = false;
        }, 2e3);
      }
      return (_ctx, _cache) => {
        const _component_PkpButton = vue.resolveComponent("PkpButton");
        const _component_icon = vue.resolveComponent("icon");
        const _component_notification = vue.resolveComponent("notification");
        const _component_PkpSearch = vue.resolveComponent("PkpSearch");
        const _component_PkpTableColumn = vue.resolveComponent("PkpTableColumn");
        const _component_PkpTableHeader = vue.resolveComponent("PkpTableHeader");
        const _component_PkpTableCell = vue.resolveComponent("PkpTableCell");
        const _component_PkpTableRow = vue.resolveComponent("PkpTableRow");
        const _component_Icon = vue.resolveComponent("Icon");
        const _component_PkpTableBody = vue.resolveComponent("PkpTableBody");
        const _component_PkpTable = vue.resolveComponent("PkpTable");
        return vue.openBlock(), vue.createElementBlock("div", _hoisted_1$1, [
          vue.createElementVNode("form", {
            ref_key: "exportForm",
            ref: exportForm,
            id: "exportXmlForm",
            class: "pkp_form dnb_form",
            action: "",
            method: "post"
          }, [
            vue.createElementVNode("div", _hoisted_2$1, [
              vue.createVNode(_component_PkpButton, {
                id: "dnb_deposit",
                onClick: _cache[0] || (_cache[0] = ($event) => handleAction("deposit")),
                disabled: selectedSubmissions.value.length === 0 || isSubmitting.value,
                class: "bg-default"
              }, {
                default: vue.withCtx(() => [
                  isSubmitting.value ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_3$1)) : vue.createCommentVNode("", true),
                  vue.createTextVNode(" " + vue.toDisplayString(__props.data.i18n.deposit), 1)
                ]),
                _: 1
              }, 8, ["disabled"]),
              vue.createVNode(_component_PkpButton, {
                id: "dnb_export",
                onClick: _cache[1] || (_cache[1] = ($event) => handleAction("export")),
                disabled: selectedSubmissions.value.length === 0 || isSubmitting.value,
                class: "bg-default"
              }, {
                default: vue.withCtx(() => [
                  isSubmitting.value ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_4$1)) : vue.createCommentVNode("", true),
                  vue.createTextVNode(" " + vue.toDisplayString(__props.data.i18n.export), 1)
                ]),
                _: 1
              }, 8, ["disabled"]),
              vue.createVNode(_component_PkpButton, {
                id: "dnb_mark",
                onClick: _cache[2] || (_cache[2] = ($event) => handleAction("mark")),
                disabled: selectedSubmissions.value.length === 0 || isSubmitting.value,
                class: "bg-default"
              }, {
                default: vue.withCtx(() => [
                  isSubmitting.value ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_5$1)) : vue.createCommentVNode("", true),
                  vue.createTextVNode(" " + vue.toDisplayString(__props.data.i18n.markRegistered), 1)
                ]),
                _: 1
              }, 8, ["disabled"]),
              vue.createVNode(_component_PkpButton, {
                id: "dnb_mark_exclude",
                onClick: _cache[3] || (_cache[3] = ($event) => handleAction("exclude")),
                disabled: selectedSubmissions.value.length === 0 || isSubmitting.value,
                class: "bg-default"
              }, {
                default: vue.withCtx(() => [
                  isSubmitting.value ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_6$1)) : vue.createCommentVNode("", true),
                  vue.createTextVNode(" " + vue.toDisplayString(__props.data.i18n.exclude), 1)
                ]),
                _: 1
              }, 8, ["disabled"]),
              vue.createVNode(_component_PkpButton, {
                onClick: toggleSelectAll,
                disabled: filteredItems.value.length === 0 || isSubmitting.value,
                class: "bg-default"
              }, {
                default: vue.withCtx(() => [
                  areAllVisibleSelected.value && filteredItems.value.length > 0 ? (vue.openBlock(), vue.createElementBlock(vue.Fragment, { key: 0 }, [
                    vue.createTextVNode(vue.toDisplayString(__props.data.i18n.selectNone), 1)
                  ], 64)) : (vue.openBlock(), vue.createElementBlock(vue.Fragment, { key: 1 }, [
                    vue.createTextVNode(vue.toDisplayString(__props.data.i18n.selectAll), 1)
                  ], 64))
                ]),
                _: 1
              }, 8, ["disabled"]),
              vue.createVNode(_component_PkpButton, {
                onClick: deselectAll,
                disabled: selectedSubmissions.value.length === 0 || isSubmitting.value,
                class: "bg-default"
              }, {
                default: vue.withCtx(() => [
                  vue.createTextVNode(vue.toDisplayString(__props.data.i18n.deselectAll), 1)
                ]),
                _: 1
              }, 8, ["disabled"])
            ]),
            (vue.openBlock(true), vue.createElementBlock(vue.Fragment, null, vue.renderList(__props.data.errors, (error, index) => {
              return vue.openBlock(), vue.createBlock(_component_notification, {
                type: "warning",
                key: index,
                style: { "margin-bottom": "1rem" }
              }, {
                default: vue.withCtx(() => [
                  vue.createVNode(_component_icon, {
                    icon: "exclamation-triangle",
                    inline: true
                  }),
                  vue.createTextVNode(" " + vue.toDisplayString(error), 1)
                ]),
                _: 2
              }, 1024);
            }), 128)),
            vue.createElementVNode("div", _hoisted_7$1, vue.toDisplayString(itemCountText.value), 1),
            vue.createVNode(_component_PkpTable, null, {
              "top-controls": vue.withCtx(() => [
                vue.createElementVNode("div", _hoisted_8, [
                  vue.createElementVNode("div", _hoisted_9, [
                    vue.createElementVNode("div", {
                      onKeydown: vue.withKeys(vue.withModifiers(addSearchFilter, ["prevent"]), ["enter"])
                    }, [
                      vue.createVNode(_component_PkpSearch, {
                        searchPhrase: searchPhrase.value,
                        searchLabel: searchLabel.value,
                        onSearchPhraseChanged: setSearchPhrase,
                        style: { "width": "unset" }
                      }, null, 8, ["searchPhrase", "searchLabel"])
                    ], 40, _hoisted_10),
                    activeSearchFilters.value.length > 0 ? (vue.openBlock(), vue.createElementBlock("div", _hoisted_11, [
                      (vue.openBlock(true), vue.createElementBlock(vue.Fragment, null, vue.renderList(activeSearchFilters.value, (filter, index) => {
                        return vue.openBlock(), vue.createElementBlock("div", {
                          key: index,
                          class: "dnb-search-filter-chip",
                          role: "status",
                          "aria-label": `Search filter: ${filter}`
                        }, [
                          vue.createElementVNode("span", _hoisted_13, vue.toDisplayString(filter), 1),
                          vue.createElementVNode("button", {
                            type: "button",
                            class: "dnb-chip-remove",
                            onClick: ($event) => removeSearchFilter(index),
                            "aria-label": `Remove search filter: ${filter}`
                          }, " × ", 8, _hoisted_14)
                        ], 8, _hoisted_12);
                      }), 128))
                    ])) : vue.createCommentVNode("", true)
                  ]),
                  vue.createElementVNode("div", _hoisted_15, [
                    vue.createElementVNode("button", {
                      type: "button",
                      onClick: _cache[4] || (_cache[4] = ($event) => setStatusFilter(null)),
                      class: vue.normalizeClass(["dnb-filter-btn", "dnb-filter-all", activeStatusFilter.value === null ? "dnb-filter-active" : ""])
                    }, vue.toDisplayString(__props.data.i18n.filterAll), 3),
                    vue.createElementVNode("button", {
                      type: "button",
                      onClick: _cache[5] || (_cache[5] = ($event) => setStatusFilter(__props.data.constants.EXPORT_STATUS_NOT_DEPOSITED)),
                      class: vue.normalizeClass(["dnb-filter-btn", "dnb-filter-not-deposited", activeStatusFilter.value === __props.data.constants.EXPORT_STATUS_NOT_DEPOSITED ? "dnb-filter-active" : ""])
                    }, vue.toDisplayString(__props.data.i18n.filterNotDeposited), 3),
                    vue.createElementVNode("button", {
                      type: "button",
                      onClick: _cache[6] || (_cache[6] = ($event) => setStatusFilter(__props.data.constants.DNB_STATUS_DEPOSITED)),
                      class: vue.normalizeClass(["dnb-filter-btn", "dnb-filter-deposited", activeStatusFilter.value === __props.data.constants.DNB_STATUS_DEPOSITED ? "dnb-filter-active" : ""])
                    }, vue.toDisplayString(__props.data.i18n.filterDeposited), 3),
                    vue.createElementVNode("button", {
                      type: "button",
                      onClick: _cache[7] || (_cache[7] = ($event) => setStatusFilter(__props.data.constants.DNB_EXPORT_STATUS_QUEUED)),
                      class: vue.normalizeClass(["dnb-filter-btn", "dnb-filter-queued", activeStatusFilter.value === __props.data.constants.DNB_EXPORT_STATUS_QUEUED ? "dnb-filter-active" : ""])
                    }, vue.toDisplayString(__props.data.i18n.filterQueued), 3),
                    vue.createElementVNode("button", {
                      type: "button",
                      onClick: _cache[8] || (_cache[8] = ($event) => setStatusFilter(__props.data.constants.DNB_EXPORT_STATUS_FAILED)),
                      class: vue.normalizeClass(["dnb-filter-btn", "dnb-filter-failed", activeStatusFilter.value === __props.data.constants.DNB_EXPORT_STATUS_FAILED ? "dnb-filter-active" : ""])
                    }, vue.toDisplayString(__props.data.i18n.filterFailed), 3),
                    vue.createElementVNode("button", {
                      type: "button",
                      onClick: _cache[9] || (_cache[9] = ($event) => setStatusFilter(__props.data.constants.EXPORT_STATUS_MARKEDREGISTERED)),
                      class: vue.normalizeClass(["dnb-filter-btn", "dnb-filter-marked", activeStatusFilter.value === __props.data.constants.EXPORT_STATUS_MARKEDREGISTERED ? "dnb-filter-active" : ""])
                    }, vue.toDisplayString(__props.data.i18n.filterMarkedRegistered), 3),
                    vue.createElementVNode("button", {
                      type: "button",
                      onClick: _cache[10] || (_cache[10] = ($event) => setStatusFilter(__props.data.constants.DNB_EXPORT_STATUS_MARKEXCLUDED)),
                      class: vue.normalizeClass(["dnb-filter-btn", "dnb-filter-excluded", activeStatusFilter.value === __props.data.constants.DNB_EXPORT_STATUS_MARKEXCLUDED ? "dnb-filter-active" : ""])
                    }, vue.toDisplayString(__props.data.i18n.filterExcluded), 3)
                  ])
                ])
              ]),
              default: vue.withCtx(() => [
                vue.createVNode(_component_PkpTableHeader, null, {
                  default: vue.withCtx(() => [
                    vue.createVNode(_component_PkpTableColumn, { id: "checkbox" }, {
                      default: vue.withCtx(() => [..._cache[12] || (_cache[12] = [
                        vue.createElementVNode("span", { class: "sr-only" }, "Select submission", -1)
                      ])]),
                      _: 1
                    }),
                    vue.createVNode(_component_PkpTableColumn, {
                      id: "id",
                      style: { "text-align": "center" }
                    }, {
                      default: vue.withCtx(() => [
                        vue.createTextVNode(vue.toDisplayString(vue.unref(t)("common.id")), 1)
                      ]),
                      _: 1
                    }),
                    vue.createVNode(_component_PkpTableColumn, { id: "details" }, {
                      default: vue.withCtx(() => [
                        vue.createTextVNode(vue.toDisplayString(vue.unref(t)("common.details")), 1)
                      ]),
                      _: 1
                    }),
                    vue.createVNode(_component_PkpTableColumn, { id: "status" }, {
                      default: vue.withCtx(() => [
                        vue.createTextVNode(vue.toDisplayString(vue.unref(t)("common.status")), 1)
                      ]),
                      _: 1
                    })
                  ]),
                  _: 1
                }),
                vue.createVNode(_component_PkpTableBody, null, {
                  default: vue.withCtx(() => [
                    filteredItems.value.length === 0 ? (vue.openBlock(), vue.createBlock(_component_PkpTableRow, { key: 0 }, {
                      default: vue.withCtx(() => [
                        vue.createVNode(_component_PkpTableCell, {
                          colspan: "4",
                          style: { "text-align": "center", "padding": "2rem", "color": "#666" }
                        }, {
                          default: vue.withCtx(() => [
                            vue.createElementVNode("div", null, [
                              vue.createElementVNode("p", _hoisted_16, vue.toDisplayString(noResultsText.value), 1),
                              hasActiveFilters.value ? (vue.openBlock(), vue.createElementBlock("p", _hoisted_17, vue.toDisplayString(__props.data.i18n.filterHint), 1)) : vue.createCommentVNode("", true)
                            ])
                          ]),
                          _: 1
                        })
                      ]),
                      _: 1
                    })) : vue.createCommentVNode("", true),
                    (vue.openBlock(true), vue.createElementBlock(vue.Fragment, null, vue.renderList(filteredItems.value, (item) => {
                      return vue.openBlock(), vue.createBlock(_component_PkpTableRow, {
                        key: item.id
                      }, {
                        default: vue.withCtx(() => [
                          vue.createVNode(_component_PkpTableCell, null, {
                            default: vue.withCtx(() => [
                              vue.withDirectives(vue.createElementVNode("input", {
                                type: "checkbox",
                                name: "selectedSubmissions[]",
                                value: item.id,
                                "onUpdate:modelValue": _cache[11] || (_cache[11] = ($event) => selectedSubmissions.value = $event),
                                "aria-label": `Select submission ${item.id}: ${item.publication.fullTitle}`
                              }, null, 8, _hoisted_18), [
                                [vue.vModelCheckbox, selectedSubmissions.value]
                              ])
                            ]),
                            _: 2
                          }, 1024),
                          vue.createVNode(_component_PkpTableCell, { style: { "text-align": "center" } }, {
                            default: vue.withCtx(() => [
                              vue.createElementVNode("span", _hoisted_19, vue.toDisplayString(item.id), 1)
                            ]),
                            _: 2
                          }, 1024),
                          vue.createVNode(_component_PkpTableCell, null, {
                            default: vue.withCtx(() => [
                              vue.createElementVNode("div", _hoisted_20, [
                                vue.createElementVNode("div", _hoisted_21, [
                                  vue.createElementVNode("span", _hoisted_22, vue.toDisplayString(item.publication.authorsString), 1),
                                  vue.createElementVNode("a", {
                                    href: item.urlWorkflow,
                                    class: "font-semibold"
                                  }, vue.toDisplayString(item.publication.fullTitle), 9, _hoisted_23),
                                  item.issueUrl ? (vue.openBlock(), vue.createElementBlock("a", {
                                    key: 0,
                                    href: item.issueUrl,
                                    class: "text-sm"
                                  }, vue.toDisplayString(item.issueTitle), 9, _hoisted_24)) : vue.createCommentVNode("", true),
                                  item.supplementariesNotAssignable ? (vue.openBlock(), vue.createElementBlock("div", _hoisted_25, [
                                    vue.createVNode(_component_Icon, {
                                      icon: "exclamation-triangle",
                                      inline: true
                                    }),
                                    vue.createElementVNode("span", _hoisted_26, vue.toDisplayString(item.supplementariesNotAssignable), 1)
                                  ])) : vue.createCommentVNode("", true),
                                  item.supplementaryNotAssignable ? (vue.openBlock(), vue.createElementBlock("div", _hoisted_27, [
                                    vue.createElementVNode("span", _hoisted_28, vue.toDisplayString(item.supplementariesNotAssignable), 1)
                                  ])) : vue.createCommentVNode("", true)
                                ]),
                                item.lastError ? (vue.openBlock(), vue.createElementBlock("div", {
                                  key: 0,
                                  class: "dnb-error-icon-wrapper",
                                  title: `${item.lastError}`
                                }, " ⚠️ ", 8, _hoisted_29)) : vue.createCommentVNode("", true)
                              ])
                            ]),
                            _: 2
                          }, 1024),
                          vue.createVNode(_component_PkpTableCell, null, {
                            default: vue.withCtx(() => [
                              item.dnbStatusConst && item.dnbStatusConst === __props.data.constants.DNB_STATUS_DEPOSITED ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_30, vue.toDisplayString(item.dnbStatus), 1)) : vue.createCommentVNode("", true),
                              item.dnbStatusConst && item.dnbStatusConst === __props.data.constants.DNB_EXPORT_STATUS_QUEUED ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_31, vue.toDisplayString(item.dnbStatus), 1)) : vue.createCommentVNode("", true),
                              item.dnbStatusConst && item.dnbStatusConst === __props.data.constants.EXPORT_STATUS_NOT_DEPOSITED ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_32, vue.toDisplayString(item.dnbStatus), 1)) : vue.createCommentVNode("", true),
                              item.dnbStatusConst && item.dnbStatusConst === __props.data.constants.DNB_EXPORT_STATUS_FAILED ? (vue.openBlock(), vue.createElementBlock("span", _hoisted_33, vue.toDisplayString(item.dnbStatus), 1)) : vue.createCommentVNode("", true)
                            ]),
                            _: 2
                          }, 1024)
                        ]),
                        _: 2
                      }, 1024);
                    }), 128))
                  ]),
                  _: 1
                })
              ]),
              _: 1
            })
          ], 512)
        ]);
      };
    }
  };
  const _export_sfc = (sfc, props) => {
    const target = sfc.__vccOpts || sfc;
    for (const [key, val] of props) {
      target[key] = val;
    }
    return target;
  };
  const _hoisted_1 = { class: "dnb-help-header" };
  const _hoisted_2 = ["aria-label"];
  const _hoisted_3 = {
    key: 0,
    class: "dnb-help-loading"
  };
  const _hoisted_4 = {
    key: 1,
    class: "dnb-help-error"
  };
  const _hoisted_5 = ["innerHTML"];
  const _hoisted_6 = {
    key: 0,
    class: "dnb-help-footer"
  };
  const _hoisted_7 = { key: 1 };
  const _sfc_main = {
    __name: "DNBHelpPanel",
    props: {
      helpApiUrl: { type: String, required: true },
      locale: { type: String, required: true }
    },
    setup(__props, { expose: __expose }) {
      const props = __props;
      const { useLocalize } = pkp.modules.useLocalize;
      const { t } = useLocalize();
      const isOpen = vue.ref(false);
      const loading = vue.ref(false);
      const error = vue.ref(null);
      const content = vue.ref("");
      const helpTitle = vue.ref("Help");
      const currentTopic = vue.ref("SUMMARY");
      const previousTopic = vue.ref(null);
      const nextTopic = vue.ref(null);
      function openHelp(topic = "SUMMARY") {
        isOpen.value = true;
        loadHelpContent(topic);
      }
      function closeHelp() {
        isOpen.value = false;
      }
      function navigateTo(topic) {
        currentTopic.value = topic;
        loadHelpContent(topic);
      }
      function handleContentClick(event) {
        const target = event.target.closest("a");
        if (!target) return;
        const href = target.getAttribute("href");
        if (!href || href.startsWith("http://") || href.startsWith("https://") || href.startsWith("#")) {
          return;
        }
        event.preventDefault();
        let topic = href.replace(/\.md$/, "");
        const lang = props.locale.substring(0, 2);
        if (topic.startsWith(lang + "/")) {
          topic = topic.substring(lang.length + 1);
        }
        navigateTo(topic);
      }
      async function loadHelpContent(topic) {
        loading.value = true;
        error.value = null;
        try {
          const lang = props.locale.substring(0, 2);
          const url = `${props.helpApiUrl}?lang=${lang}&topic=${topic}`;
          const response = await fetch(url, {
            method: "GET",
            headers: {
              "Accept": "application/json"
            },
            credentials: "same-origin"
          });
          if (!response.ok) {
            throw new Error(`Failed to load help content (${response.status})`);
          }
          const data = await response.json();
          const titleMatch = data.content.match(/<h1[^>]*>(.*?)<\/h1>/i);
          if (titleMatch) {
            helpTitle.value = titleMatch[1].replace(/<[^>]*>/g, "");
            content.value = data.content.replace(/<h1[^>]*>.*?<\/h1>/i, "");
          } else {
            content.value = data.content;
            helpTitle.value = "Help";
          }
          previousTopic.value = data.previous;
          nextTopic.value = data.next;
          currentTopic.value = topic;
        } catch (e) {
          console.error("DNB Help Panel Error:", e);
          error.value = t("common.error.loadingFailed") || "Failed to load help content";
        } finally {
          loading.value = false;
        }
      }
      __expose({
        openHelp,
        closeHelp
      });
      return (_ctx, _cache) => {
        return vue.openBlock(), vue.createBlock(vue.Teleport, { to: "body" }, [
          isOpen.value ? (vue.openBlock(), vue.createElementBlock("div", {
            key: 0,
            class: "dnb-help-overlay",
            onClick: closeHelp
          }, [
            vue.createElementVNode("div", {
              class: "dnb-help-panel",
              onClick: _cache[2] || (_cache[2] = vue.withModifiers(() => {
              }, ["stop"]))
            }, [
              vue.createElementVNode("div", _hoisted_1, [
                vue.createElementVNode("h2", null, vue.toDisplayString(helpTitle.value), 1),
                vue.createElementVNode("button", {
                  type: "button",
                  class: "dnb-help-close",
                  onClick: closeHelp,
                  "aria-label": vue.unref(t)("common.close")
                }, " × ", 8, _hoisted_2)
              ]),
              vue.createElementVNode("div", {
                class: "dnb-help-content",
                onClick: handleContentClick
              }, [
                loading.value ? (vue.openBlock(), vue.createElementBlock("div", _hoisted_3, [
                  _cache[3] || (_cache[3] = vue.createElementVNode("div", { class: "dnb-spinner" }, null, -1)),
                  vue.createElementVNode("p", null, vue.toDisplayString(vue.unref(t)("common.loading")), 1)
                ])) : error.value ? (vue.openBlock(), vue.createElementBlock("div", _hoisted_4, [
                  vue.createElementVNode("p", null, vue.toDisplayString(error.value), 1)
                ])) : (vue.openBlock(), vue.createElementBlock("div", {
                  key: 2,
                  innerHTML: content.value,
                  class: "dnb-help-markdown"
                }, null, 8, _hoisted_5))
              ]),
              !loading.value && !error.value ? (vue.openBlock(), vue.createElementBlock("div", _hoisted_6, [
                previousTopic.value ? (vue.openBlock(), vue.createElementBlock("button", {
                  key: 0,
                  type: "button",
                  class: "dnb-help-nav-btn",
                  onClick: _cache[0] || (_cache[0] = ($event) => navigateTo(previousTopic.value))
                }, " ← " + vue.toDisplayString(vue.unref(t)("common.previous")), 1)) : (vue.openBlock(), vue.createElementBlock("span", _hoisted_7)),
                nextTopic.value ? (vue.openBlock(), vue.createElementBlock("button", {
                  key: 2,
                  type: "button",
                  class: "dnb-help-nav-btn",
                  onClick: _cache[1] || (_cache[1] = ($event) => navigateTo(nextTopic.value))
                }, vue.toDisplayString(vue.unref(t)("common.next")) + " → ", 1)) : vue.createCommentVNode("", true)
              ])) : vue.createCommentVNode("", true)
            ])
          ])) : vue.createCommentVNode("", true)
        ]);
      };
    }
  };
  const DNBHelpPanel = /* @__PURE__ */ _export_sfc(_sfc_main, [["__scopeId", "data-v-162f30be"]]);
  pkp.registry.registerComponent("dnb-submissions-table", _sfc_main$1);
  pkp.registry.registerComponent("dnb-help-panel", DNBHelpPanel);
})(pkp.modules.vue);
