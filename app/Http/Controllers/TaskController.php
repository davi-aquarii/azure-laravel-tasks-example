<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        return Task::orderBy('order')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Get the highest order value
        $maxOrder = Task::max('order') ?? -1;

        $task = Task::create([
            'name' => $request->name,
            'completed' => false,
            'order' => $maxOrder + 1
        ]);

        return $task;
    }

    public function update(Request $request, Task $task)
    {
        $request->validate([
            'completed' => 'required|boolean',
        ]);

        $task->update([
            'completed' => $request->completed,
        ]);

        return $task;
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json(null, 204);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:tasks,id',
            'tasks.*.order' => 'required|integer|min:0',
            'tasks.*.from_position' => 'nullable|integer|min:1',
            'tasks.*.to_position' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            // Encontra a tarefa que está sendo movida
            $movedTaskData = collect($request->tasks)->first(function ($task) {
                return !is_null($task['from_position']);
            });

            if (!$movedTaskData) {
                throw new \Exception('Could not identify the moved task');
            }

            $movedTask = Task::find($movedTaskData['id']);
            $fromPosition = $movedTaskData['from_position'];
            $toPosition = $movedTaskData['to_position'];

            // Atualiza a ordem de todas as tarefas
            foreach ($request->tasks as $index => $taskData) {
                Task::where('id', $taskData['id'])->update(['order' => $index]);
            }

            DB::commit();

            // Retorna a lista atualizada de tarefas
            $updatedTasks = Task::orderBy('order')->get();

            return response()->json([
                'message' => "Task '{$movedTask->name}' moved from position {$fromPosition} to {$toPosition}",
                'tasks' => $updatedTasks
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error reordering tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
