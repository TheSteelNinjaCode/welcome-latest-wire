<?php

use Lib\Prisma\Classes\Prisma;
use Lib\Validator;
use Lib\StateManager;

$prisma = new Prisma();
$state = new StateManager();

$isUpdate = $state->getState('isUpdate') ?? false;
$search = $state->getState('search') ?? '';

$todos = $prisma->todo->findMany([
    'where' => [
        'title' => [
            'contains' => $search
        ]
    ]
], true);

function searchTodo($data)
{
    global $state;
    $state->setState('search', $data->search ?? '');
}

function isUpdateMode($data)
{
    global $state;
    $updateMode = $data->args[0] ?? false;
    $id = $data->args[1] ?? '';
    $title = $data->args[2] ?? '';
    if (!Validator::boolean($updateMode) || empty($id) || empty($title)) return;
    $state->setState('isUpdate', $updateMode);
    $state->setState('id', $id);
    $state->setState('title', $title);
}

function completed($data)
{
    global $prisma;
    $id = $data->args[0] ?? '';
    $completed = $data->completed;
    if (!is_string($id) && !is_bool($completed)) return;
    $prisma->todo->update([
        'where' => ['id' => $id],
        'data' => ['completed' => $completed]
    ]);
}

function delete($data)
{
    global $prisma;
    $id = $data->args[0] ?? '';
    if (!Validator::string($id)) return;
    $prisma->todo->delete(['where' => ['id' => $id]]);
}

?>

<div class="flex flex-col items-center justify-center h-screen bg-gray-100 dark:bg-gray-900">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <div class="flex gap-4 justify-between mb-4 items-center w-full">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Todo List</h1>
            <input placeholder="Search todos..." class="px-4 p-2 rounded-md bg-gray-100 dark:bg-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500" name="search" type="search" oninput="searchTodo" value="<?= $search ?>" pp-debounce="400" />
        </div>
        <?php
        if ($isUpdate)
            require_once APP_PATH . '/components/update.php';
        else
            require_once APP_PATH . '/components/create.php';
        ?>
        <div class="space-y-2 h-48 overflow-auto">
            <?php foreach ($todos as $todo) : ?>
                <div class="flex items-center justify-between bg-gray-100 dark:bg-gray-700 rounded-md p-2">
                    <div class="flex items-center">
                        <input id="<?= $todo->id ?>" type="checkbox" class="mr-2 text-blue-500 focus:ring-blue-500 focus:ring-2 rounded" name="completed" onchange="completed('<?= $todo->id ?>')" <?= $todo->completed ? 'checked' : '' ?> />
                        <span class="<?= $todo->completed ? 'line-through text-gray-500 dark:text-gray-400' : 'text-gray-800 dark:text-gray-200' ?>"><?= $todo->title ?></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button class="text-yellow-500 hover:text-yellow-600" onclick="isUpdateMode('true', '<?= $todo->id ?>', '<?= $todo->title ?>')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <path d="M20 5H9l-7 7 7 7h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z"></path>
                                <line x1="18" x2="12" y1="9" y2="15"></line>
                                <line x1="12" x2="18" y1="9" y2="15"></line>
                            </svg>
                        </button>
                        <button class="text-red-500 hover:text-red-600" onclick="delete('<?= $todo->id ?>')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                <path d="M3 6h18"></path>
                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>