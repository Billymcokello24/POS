<template>
  <div v-if="message" class="flash-message">
    {{ message }}
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
const props = defineProps({ message: { type: String, default: '' } })
const message = ref(props.message)

onMounted(() => {
  // placeholder: real flash system can set message via store/events
})
</script>

<style scoped>
.flash-message {
  padding: 0.5rem 1rem;
  border-radius: 0.375rem;
  background-color: #f1f5f9;
  color: #0f172a;
}
</style>
