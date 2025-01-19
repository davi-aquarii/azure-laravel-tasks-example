<template>
  <div :class="{ 'dark': isDark }" class="min-h-screen">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
      <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Task Manager</h1>
          <div class="flex items-center gap-4">
            <button
              @click="toggleDarkMode"
              class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200"
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
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
          <form @submit.prevent="addTask" class="flex gap-4">
            <input
              v-model="newTask"
              type="text"
              placeholder="What needs to be done?"
              class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
              :disabled="isLoading"
              required
            />
            <button
              type="submit"
              class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 dark:bg-blue-500 dark:hover:bg-blue-600"
              :disabled="isLoading"
            >
              <span v-if="isLoading" class="inline-block animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              {{ isLoading ? 'Adding...' : 'Add Task' }}
            </button>
          </form>
        </div>

        <!-- Task List -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
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
            class="divide-y divide-gray-200 rounded-lg dark:divide-gray-700"
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
                class="p-6 flex items-center justify-between gap-4 group transition-all duration-200 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg"
                :class="{
                  'opacity-50': loadingTasks.includes(task.id),
                  'ghost': isReordering && index === dragStartIndex.value
                }"
              >
                <div class="flex items-center gap-4 flex-1">
                  <button
                    @click="toggleTask(task)"
                    class="flex-shrink-0 w-5 h-5 border-2 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                    :class="{
                      'border-gray-300 dark:border-gray-600': !task.completed,
                      'bg-green-500 border-green-500': task.completed
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
                    class="flex-1 text-gray-900 dark:text-white"
                    :class="{ 'line-through text-gray-500': task.completed }"
                  >
                    {{ task.name }}
                    <span class="task-position ml-2">
                      Position: {{ index + 1 }}
                      <template v-if="isReordering && task.id === draggedTaskId">
                        → {{ newDragPosition }}
                      </template>
                    </span>
                  </span>
                </div>
                <button
                  @click="deleteTask(task)"
                  class="opacity-0 group-hover:opacity-100 focus:opacity-100 p-2 text-gray-500 hover:text-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 rounded-full transition-all duration-200"
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import draggable from 'vuedraggable'
import axios from 'axios'

// Estado
const tasks = ref([])
const newTask = ref('')
const isDark = ref(localStorage.getItem('darkMode') === 'true')
const isLoading = ref(false)
const isInitialLoading = ref(true)
const loadingTasks = ref([])
const lastRequestTime = ref(null)

// Estado do drag and drop
const isReordering = ref(false)
const dragStartIndex = ref(null)
const draggedTaskId = ref(null)
const newDragPosition = ref(null)

// Opções do draggable
const dragOptions = {
  animation: 200,
  group: 'tasks',
  disabled: false,
  ghostClass: 'ghost'
}

// Funções auxiliares
const timedAxios = async (fn) => {
  const start = performance.now()
  try {
    const response = await fn()
    lastRequestTime.value = Math.round(performance.now() - start)
    return response
  } catch (error) {
    lastRequestTime.value = Math.round(performance.now() - start)
    throw error
  }
}

// Funções principais
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

const toggleTask = async (task) => {
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
    console.error('Error toggling task:', error)
  } finally {
    loadingTasks.value = loadingTasks.value.filter(id => id !== task.id)
  }
}

const deleteTask = async (task) => {
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

const toggleDarkMode = () => {
  isDark.value = !isDark.value
  localStorage.setItem('darkMode', isDark.value)
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

// Inicialização
onMounted(() => {
  fetchTasks()
})
</script>

<style>
.flip-list-move {
  transition: transform 0.3s;
}

.ghost {
  opacity: 0.5;
  background: #c8ebfb;
}

.ghost.sortable-ghost {
  opacity: 0;
}

.sortable-drag {
  background: #f8fafc;
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}

.task-position {
  @apply text-gray-400 dark:text-gray-500 text-sm;
}
</style>
