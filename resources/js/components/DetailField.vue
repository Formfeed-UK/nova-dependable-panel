<template>
  <div class="divide-y divide-gray-100 dark:divide-gray-700">
    <component
      :key="index"
      v-for="(field, index) in field.fields"
      :index="index"
      :is="resolveComponentName(field)"
      :resource-name="resourceName"
      :resource-id="resourceId"
      :resource="resource"
      :field="field"
      @actionExecuted="actionExecuted"
    />
  </div>
</template>

<script>
export default {
  props: ["index", "resource", "resourceName", "resourceId", "field"],
  methods: {
    /**
     * Resolve the component name.
     */
    resolveComponentName(field) {
      return field.prefixComponent
        ? "detail-" + field.component
        : field.component;
    },
  },
};
</script>
