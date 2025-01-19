<template>
  <div :class="{ 'dark': isDark }" class="task-manager">
    <div class="task-container">
      <!-- Header -->
      <div class="task-header">
        <h1 class="task-title">Task Manager</h1>
        <div class="flex items-center gap-4">
          <button
            @click="toggleDarkMode"
            class="theme-button"
            :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
          >
            <svg
              v-if="isDark"
              class="w-5 h-5 text-gray-300"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
              />
            </svg>
            <svg
              v-else
              class="w-5 h-5 text-gray-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
              />
            </svg>
          </button>
          <div v-if="lastRequestTime" class="text-sm text-gray-500 dark:text-gray-400">
            Last request: {{ lastRequestTime }}ms
          </div>
        </div>
      </div>

      <!-- New Task Form -->
      <div class="task-form">
        <form @submit.prevent="addTask" class="flex gap-4">
          <input
            v-model="newTask"
            type="text"
            placeholder="What needs to be done?"
            class="task-input"
            :disabled="isLoading"
            required
          />
          <button
            type="submit"
            class="task-button"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="inline-block animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            {{ isLoading ? 'Adding...' : 'Add Task' }}
          </button>
        </form>
      </div>

      <!-- Task List -->
      <div class="task-list">
        <div v-if="isInitialLoading" class="p-6 text-center text-gray-500 dark:text-gray-400">
          <div class="inline-block animate-spin h-6 w-6 border-2 border-blue-600 dark:border-blue-400 border-t-transparent rounded-full mb-2"></div>
          <p>Loading tasks...</p>
        </div>
        <div v-else-if="tasks.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
          No tasks yet. Add one above!
        </div>
        <draggable
          v-model="tasks"
          v-bind="dragOptions"
          class="divide-y divide-gray-200 dark:divide-gray-700"
          item-key="id"
          @start="dragStart"
          @change="handleDragChange"
          @end="dragEnd"
          :component-data="{
            tag: 'ul',
            type: 'transition-group',
          }"
        >
          <template #item="{ element: task, index }">
            <li
              :key="task.id"
              class="task-item"
              :class="{
                'opacity-50': loadingTasks.includes(task.id),
                'ghost': isReordering && index === dragStartIndex.value
              }"
            >
              <div class="flex items-center gap-4 flex-1">
                <button
                  @click="toggleTask(task)"
                  class="task-checkbox"
                  :class="{
                    'completed': task.completed
                  }"
                  :disabled="loadingTasks.includes(task.id)"
                >
                  <svg
                    v-if="task.completed"
                    class="w-full h-full text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                </button>
                <span
                  class="task-text"
                  :class="{ 'completed': task.completed }"
                >
                  {{ task.name }}
                  <span class="task-position">
                    Position: {{ index + 1 }}
                    <template v-if="isReordering && task.id === draggedTaskId">
                      → {{ newDragPosition }}
                    </template>
                  </span>
                </span>
              </div>
              <button
                @click="deleteTask(task)"
                class="task-delete"
                :disabled="loadingTasks.includes(task.id)"
              >
                <svg
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                  />
                </svg>
              </button>
            </li>
          </template>
        </draggable>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import draggable from 'vuedraggable'

const tasks = ref([])
const newTask = ref('')
const isLoading = ref(false)
const isInitialLoading = ref(true)
const loadingTasks = ref([])
const isReordering = ref(false)
const lastRequestTime = ref(null)
const isDark = ref(false)
const dragStartIndex = ref(null)
const draggedTaskId = ref(null)
const newDragPosition = ref(null)

// Toggle dark mode
const toggleDarkMode = () => {
  isDark.value = !isDark.value
}

// Timer wrapper for axios requests
const timedAxios = async (axiosCall) => {
  const start = performance.now()
  try {
    const response = await axiosCall()
    lastRequestTime.value = Math.round(performance.now() - start)
    return response
  } catch (error) {
    lastRequestTime.value = Math.round(performance.now() - start)
    throw error
  }
}

// Fetch all tasks
const fetchTasks = async () => {
  try {
    const response = await timedAxios(() => axios.get('/api/tasks'))
    tasks.value = response.data
  } catch (error) {
    console.error('Error fetching tasks:', error)
  } finally {
    isInitialLoading.value = false
  }
}

// Add a new task
const addTask = async () => {
  if (!newTask.value.trim()) return

  isLoading.value = true
  try {
    const response = await timedAxios(() => 
      axios.post('/api/tasks', {
        name: newTask.value
      })
    )
    tasks.value.push(response.data)
    newTask.value = ''
  } catch (error) {
    console.error('Error adding task:', error)
  } finally {
    isLoading.value = false
  }
}

// Toggle task completion
const toggleTask = async (task) => {
  if (loadingTasks.value.includes(task.id)) return

  loadingTasks.value.push(task.id)
  try {
    const response = await timedAxios(() => 
      axios.put(`/api/tasks/${task.id}`, {
        completed: !task.completed
      })
    )
    const index = tasks.value.findIndex(t => t.id === task.id)
    if (index !== -1) {
      tasks.value[index] = response.data
    }
  } catch (error) {
    console.error('Error updating task:', error)
  } finally {
    loadingTasks.value = loadingTasks.value.filter(id => id !== task.id)
  }
}

// Delete a task
const deleteTask = async (task) => {
  if (loadingTasks.value.includes(task.id)) return

  loadingTasks.value.push(task.id)
  try {
    await timedAxios(() => axios.delete(`/api/tasks/${task.id}`))
    tasks.value = tasks.value.filter(t => t.id !== task.id)
  } catch (error) {
    console.error('Error deleting task:', error)
  } finally {
    loadingTasks.value = loadingTasks.value.filter(id => id !== task.id)
  }
}

// Drag and drop handlers
const dragStart = (e) => {
  isReordering.value = true
  dragStartIndex.value = e.oldIndex
  draggedTaskId.value = tasks.value[e.oldIndex].id
}

const handleDragChange = (e) => {
  if (e.moved) {
    newDragPosition.value = e.moved.newIndex + 1
  } else if (e.added) {
    newDragPosition.value = e.added.newIndex + 1
  }
}

const dragEnd = async (e) => {
  if (!isReordering.value) return

  // Se a posição inicial e final são iguais, não faz nada
  if (e.oldIndex === e.newIndex) {
    isReordering.value = false
    loadingTasks.value = []
    dragStartIndex.value = null
    draggedTaskId.value = null
    newDragPosition.value = null
    return
  }

  try {
    loadingTasks.value = tasks.value.map(t => t.id)
    
    // Identifica a tarefa movida e suas posições
    const movedTaskId = draggedTaskId.value
    const fromPosition = dragStartIndex.value + 1
    const toPosition = newDragPosition.value

    const reorderedTasks = tasks.value.map((task, index) => ({
      id: task.id,
      order: index,
      from_position: task.id === movedTaskId ? fromPosition : null,
      to_position: task.id === movedTaskId ? toPosition : index + 1
    }))

    const response = await timedAxios(() => 
      axios.post('/api/tasks/reorder', {
        tasks: reorderedTasks
      })
    )
    
    if (response.data.tasks) {
      tasks.value = response.data.tasks
    }
  } catch (error) {
    await fetchTasks()
  } finally {
    isReordering.value = false
    loadingTasks.value = []
    dragStartIndex.value = null
    draggedTaskId.value = null
    newDragPosition.value = null
  }
}

// Load tasks when component mounts
onMounted(fetchTasks)

const dragOptions = {
  animation: 200,
  group: 'tasks',
  disabled: false,
  ghostClass: 'ghost',
}
</script>

<style scoped>
:root {
  --color-primary: theme('colors.blue.600');
  --color-primary-hover: theme('colors.blue.700');
  --color-danger: theme('colors.red.500');
  --color-danger-hover: theme('colors.red.600');
  --color-success: theme('colors.green.500');
  --color-background: theme('colors.white');
  --color-background-hover: theme('colors.gray.50');
  --color-text: theme('colors.gray.900');
  --color-text-secondary: theme('colors.gray.500');
  --color-border: theme('colors.gray.200');
  --shadow-default: theme('boxShadow.md');
  --radius-default: theme('borderRadius.md');
}

.dark {
  --color-primary: theme('colors.blue.500');
  --color-primary-hover: theme('colors.blue.600');
  --color-background: theme('colors.gray.800');
  --color-background-hover: theme('colors.gray.700');
  --color-text: theme('colors.white');
  --color-text-secondary: theme('colors.gray.400');
  --color-border: theme('colors.gray.700');
}

.task-manager {
  @apply min-h-screen;
  background-color: var(--color-background);
  color: var(--color-text);
  transition: background-color 0.2s, color 0.2s;
}

.task-container {
  @apply container mx-auto px-4 py-8;
}

.task-header {
  @apply mb-8 flex justify-between items-center;
}

.task-title {
  @apply text-3xl font-bold;
  color: var(--color-text);
}

.theme-button {
  @apply p-2 rounded-full transition-colors duration-200;
  &:hover {
    background-color: var(--color-background-hover);
  }
}

.task-form {
  @apply bg-white rounded-lg shadow-md p-6 mb-8;
  background-color: var(--color-background);
  box-shadow: var(--shadow-default);
}

.task-input {
  @apply flex-1 px-4 py-2 rounded-md focus:outline-none focus:ring-2;
  background-color: var(--color-background);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  &:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px rgba(var(--color-primary), 0.2);
  }
}

.task-button {
  @apply px-6 py-2 rounded-md transition-colors duration-200;
  background-color: var(--color-primary);
  color: white;
  &:hover:not(:disabled) {
    background-color: var(--color-primary-hover);
  }
  &:disabled {
    @apply opacity-50 cursor-not-allowed;
  }
}

.task-list {
  @apply bg-white rounded-lg shadow-md;
  background-color: var(--color-background);
  box-shadow: var(--shadow-default);
}

.task-item {
  @apply p-6 flex items-center justify-between gap-4 transition-all duration-200;
  border-bottom: 1px solid var(--color-border);
  &:hover {
    background-color: var(--color-background-hover);
  }
  &:last-child {
    border-bottom: none;
  }
}

.task-checkbox {
  @apply flex-shrink-0 w-5 h-5 border-2 rounded-full transition-colors duration-200;
  &.completed {
    background-color: var(--color-success);
    border-color: var(--color-success);
  }
}

.task-text {
  @apply flex-1;
  color: var(--color-text);
  &.completed {
    @apply line-through;
    color: var(--color-text-secondary);
  }
}

.task-position {
  @apply text-sm;
  color: var(--color-text-secondary);
}

.task-delete {
  @apply opacity-0 p-2 rounded-full transition-all duration-200;
  color: var(--color-text-secondary);
  &:hover {
    color: var(--color-danger);
  }
  .task-item:hover & {
    @apply opacity-100;
  }
}

.ghost {
  opacity: 0.5;
  background: #c8ebfb;
}

.ghost.sortable-ghost {
  opacity: 0;
}

.sortable-drag {
  background: var(--color-background);
  box-shadow: var(--shadow-default);
}

.flip-list-move {
  transition: transform 0.3s;
}

.loading-spinner {
  @apply inline-block animate-spin;
  border: 2px solid var(--color-primary);
  border-top-color: transparent;
  border-radius: 50%;
}
</style>
