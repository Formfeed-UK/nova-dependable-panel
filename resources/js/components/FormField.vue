<template>
  <div class="divide-y divide-gray-100 dark:divide-gray-700" v-show="visible">
    <component
      v-for="(field, index) in currentFields"
      :index="index"
      :ref="field.attribute"
      :key="field.dependentComponentKey"
      :is="`form-${field.component}`"
      :errors="errors"
      :resource-id="resourceId"
      :resource-name="resourceName"
      :related-resource-name="relatedResourceName"
      :related-resource-id="relatedResourceId"
      :field="field"
      :via-resource="viaResource"
      :via-resource-id="viaResourceId"
      :via-relationship="viaRelationship"
      :shown-via-new-relation-modal="shownViaNewRelationModal"
      :form-unique-id="formUniqueId"
      :mode="mode"
      @field-shown="handleFieldShown"
      @field-hidden="handleFieldHidden"
      @field-changed="$emit('field-changed')"
      @file-deleted="$emit('update-last-retrieved-at-timestamp')"
      @file-upload-started="$emit('file-upload-started')"
      @file-upload-finished="$emit('file-upload-finished')"
      :show-help-text="showHelpText"
    />
  </div>
</template>

<script>
import { DependentFormField, mapProps } from "laravel-nova";

import { Errors } from "form-backend-validation";

import each from "lodash/each";
import isEmpty from "lodash/isEmpty";
import isNil from "lodash/isNil";
import cloneDeep from "lodash/cloneDeep";
import forIn from "lodash/forIn";

export default {
  mixins: [DependentFormField],

  emits: [
    "field-changed",
    "update-last-retrieved-at-timestamp",
    "file-upload-started",
    "file-upload-finished",
  ],

  data: () => ({
    values: {},
    visibleFieldsForPanel: {},
  }),

  props: {
    shownViaNewRelationModal: {
      type: Boolean,
      default: false,
    },

    errors: {
      default: () => new Errors(),
    },

    showHelpText: {
      type: Boolean,
      default: false,
    },

    panel: {
      type: Object,
      required: true,
    },

    name: {
      default: "Panel",
    },

    ...mapProps(["mode"]),

    fields: {
      type: Array,
      default: [],
    },

    formUniqueId: {
      type: String,
    },

    validationErrors: {
      type: Object,
      required: true,
    },

    resourceName: {
      type: String,
      required: true,
    },

    resourceId: {
      type: [Number, String],
    },

    relatedResourceName: {
      type: String,
    },

    relatedResourceId: {
      type: [Number, String],
    },

    viaResource: {
      type: String,
    },

    viaResourceId: {
      type: [Number, String],
    },

    viaRelationship: {
      type: String,
    },
  },

  created() {

  },

  mounted() {
    this.setFieldValues();
    this.initGroupedDependsOn();
    this.watchFields();
    this.initialFieldVisibility();
    if (this.isInFlexibleGroup) {
      this.watchKeys();
    }
  },

  updated() {
    this.watchFields();
    //this.setFieldValues();
    this.removeEventWatchers();
    this.initGroupedDependsOn();
    if (this.currentField.singleRequest) {
        this.initialFieldVisibility();
    }
  },

  beforeUnmount() {
    this.removeEventWatchers();
  },

  methods: {
    /*
     * Set the initial, internal value for the field.
     */
    setInitialValue() {
      this.value = this.field.value || "";
    },

    initialFieldVisibility() {
        each(this.currentField.fields, field => {
            this.visibleFieldsForPanel[field.attribute] = field.visible
        })
    },

    handleFieldShown(field) {
      this.visibleFieldsForPanel[field] = true
    },

    handleFieldHidden(field) {
      this.visibleFieldsForPanel[field] = false
    },

    removeEventWatchers() {
      if (!isEmpty(this.watchedEvents)) {
        forIn(this.watchedEvents, (event, dependsOn) => {
          Nova.$off(
            this.getFieldAttributeChangeEventName(event.dependsOn),
            event
          );
        });
      }
    },

    /**
     * Fill the given FormData object with the field's internal value.
     */

    fill(formData) {
      if (this.visible) {
        for (const field in this.fieldInstances()) {
          try {
            this.fieldInstances()[field].fill(formData);
          } catch (e) {}
        }
        formData.append("_dependent_field", true);
      }
    },

    fieldInstances() {
      let fields = {};
      for (const field in this.$refs) {
        fields[field] = this.$refs[field]?.[0];
      }
      return fields;
    },

    onSyncedField() {
      this.initGroupedDependsOn();
      delete this.watchedFields["_changedField"];
      for (const field of this.currentField.fields) {
        if (this.fieldInstances().hasOwnProperty(field.attribute) && !isNil(this.fieldInstances()[field.attribute])) {
            let instance = this.fieldInstances()[field.attribute];
            field.value = isNil(field.value) ? this.values[field.attribute] ?? field.defaultValue ?? instance.currentField.value : field.value;
            instance.syncedField = field;
            instance.setInitialValue();
        }
        else {
            field.value = isNil(field.value) ? this.values[field.attribute] ?? field.defaultValue ?? field.value ?? null : field.value;
        }
      }
    },

    initGroupedDependsOn() {
      if (!this.currentField.singleRequest) {
        return;
      }
      if (!isEmpty(this.dependsOnGroups)) {
        for (let dependsOn in this.dependsOnGroups) {
          if (this.watchedEvents[dependsOn]) continue;
          this.watchedEvents[dependsOn] = (value) => {
            this.watchedFields[dependsOn] = value;
            this.dependentFieldDebouncer(() => {
              this.watchedFields["_changedField"] = dependsOn;
              this.watchedFields[dependsOn] = value;
              this.syncField();
            });
          };
          Nova.$on(
            this.getFieldAttributeChangeEventName(dependsOn),
            this.watchedEvents[dependsOn]
          );
        }
      }
    },

    watchFields() {
      for (const field in this.fieldInstances()) {
        this.$watch(
          () => this.fieldInstances()[field]?.value,
          (value) => {
            this.values[field] = value;
          }
        );
      }
    },

    watchKeys() {
      this.$watch(
        () => this.currentField.validationKey,
        (value) => {
          if (this.syncedField)
            this.syncedField.validationKey = this.field.validationKey;
        }
      );

      this.$watch(
        () => this.currentField.uniqueKey,
        (value) => {
          if (this.syncedField)
            this.syncedField.uniqueKey = this.field.uniqueKey;
        }
      );
    },

    setFieldValues() {
      for (const field of this.currentField.fields) {
        const currField = this.fieldInstances()[field.attribute];

        if (isNil(field.value) && !isNil(field.defaultValue)) {
            field.value = field.defaultValue;
        }

        if (!isNil(field.value)) {
          this.values[field.attribute] = field.value;
          if (!isNil(currField)) {
            currField.setInitialValue();
          }
        }
      }
    },

    setFieldDependentGroup(field) {
      if (!this.currentField.singleRequest) {
        return;
      }
      field.dependsOnGroups = cloneDeep(field.dependsOn);
      field.dependsOn = null;
    },
  },

  computed: {
    currentFields() {
      let fields = cloneDeep(this.currentField.fields);
      let instances = this.fieldInstances();
      fields.forEach((field) => {
        field.panel = this.panel;
        field.dependentComponentKey = `dependent_panel.${this.currentField.attribute}.${field.dependentComponentKey}`;
        if (this.isInFlexibleGroup) {
          field.validationKey = `${this.groupKey}__${field.validationKey}`;
          field.uniqueKey = `${this.groupKey}-${field.uniqueKey}`;
        }
        this.setFieldDependentGroup(field);
      });
      return fields;
    },
    dependsOnGroups() {
      let groupedDependsOn = {};
      each(this.currentFields, (field) => {
        if (field.dependsOnGroups) {
          each(field.dependsOnGroups, (defaultValue, dependsOn) => {
            if (!groupedDependsOn[dependsOn]) {
              groupedDependsOn[dependsOn] = [];
            }
            groupedDependsOn[dependsOn].push(field.attribute);
            if (!isNil(defaultValue))
              this.watchedFields[dependsOn] = defaultValue;
          });
        }
      });
      return groupedDependsOn;
    },
    currentInstances() {
      return this.fieldInstances();
    },
    visibleFieldsCount() {
      return Object.values(this.visibleFieldsForPanel).filter((visible) => visible).length;
    },
    visible() {
      let isVisible = !(
        !this.currentField.visible ||
        this.visibleFieldsCount === 0 ||
        this.currentFields.length === 0
      );

      this.$emit(
              isVisible === true
                ? 'field-shown'
                : 'field-hidden',
              this.field.attribute
            );
      return isVisible;
    },
    groupKey() {
      if (this.currentField.validationKey.includes("__")) {
        return this.currentField.validationKey.split("__")[0];
      }
      return false;
    },
    uniquePrefix() {
      const split = this.currentField.uniqueKey.split("-");
      if (split[1] == this.currentField.attribute) {
        return split[0];
      }
      return false;
    },
    isInFlexibleGroup() {
      return (
        this.groupKey !== false &&
        this.uniquePrefix !== false &&
        this.groupKey == this.uniquePrefix
      );
    },
  },
};
</script>
