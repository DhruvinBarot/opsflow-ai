@php($editing = isset($requestId))

<x-layouts.app :title="$editing ? 'Edit request' : 'New request'" :heading="$editing ? 'Edit request #'.$requestId : 'New request'"
               :description="$editing ? 'Changes are recorded in the request’s audit trail.' : 'Describe the work clearly. Once submitted, AI analysis suggests a category, risk and owning team for reviewers.'">
    <div x-data="requestForm(@js($requestId ?? null))" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <template x-if="loadError">
            <div class="lg:col-span-2">@include('partials.error-banner', ['message' => 'loadError'])</div>
        </template>

        <form class="card grid gap-5 p-5 sm:p-6" @submit.prevent="save(false)" novalidate x-show="!loading" x-cloak>
            <div>
                <label for="title" class="field-label">Title</label>
                <input id="title" type="text" class="input" maxlength="160" required x-model="form.title"
                       placeholder="e.g. Production API latency above 2s" :aria-invalid="error('title') ? 'true' : 'false'" aria-describedby="title-hint title-error">
                <div class="flex justify-between gap-3">
                    <p id="title-error" class="field-error" x-show="error('title')" x-text="error('title')"></p>
                    <p id="title-hint" class="field-hint ml-auto tabular-nums" x-text="`${form.title.length}/160`"></p>
                </div>
            </div>

            <div>
                <label for="description" class="field-label">Description</label>
                <textarea id="description" rows="7" class="input" maxlength="5000" required x-model="form.description"
                          placeholder="What is happening, who is affected, and what outcome you need." :aria-invalid="error('description') ? 'true' : 'false'" aria-describedby="description-hint description-error"></textarea>
                <div class="flex justify-between gap-3">
                    <p id="description-error" class="field-error" x-show="error('description')" x-text="error('description')"></p>
                    <p id="description-hint" class="field-hint ml-auto tabular-nums" x-text="`${form.description.length}/5000`"></p>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="category" class="field-label">Category</label>
                    <select id="category" class="input" required x-model="form.category" :aria-invalid="error('category') ? 'true' : 'false'" aria-describedby="category-error">
                        <option value="" disabled>Choose a category</option>
                        @foreach (\App\Enums\RequestCategory::cases() as $case)
                            <option value="{{ $case->value }}" x-text="$fmt.category('{{ $case->value }}')"></option>
                        @endforeach
                    </select>
                    <p id="category-error" class="field-error" x-show="error('category')" x-text="error('category')"></p>
                </div>

                <div>
                    <label for="priority" class="field-label">Priority</label>
                    <select id="priority" class="input" x-model="form.priority" :aria-invalid="error('priority') ? 'true' : 'false'" aria-describedby="priority-error">
                        @foreach (\App\Enums\RequestPriority::cases() as $case)
                            <option value="{{ $case->value }}" x-text="$fmt.priority('{{ $case->value }}')"></option>
                        @endforeach
                    </select>
                    <p id="priority-error" class="field-error" x-show="error('priority')" x-text="error('priority')"></p>
                </div>
            </div>

            <div class="sm:max-w-xs">
                <label for="due_at" class="field-label">Due <span class="font-normal text-muted">(optional)</span></label>
                <input id="due_at" type="datetime-local" class="input" x-model="form.due_at" :aria-invalid="error('due_at') ? 'true' : 'false'" aria-describedby="due-error">
                <p id="due-error" class="field-error" x-show="error('due_at')" x-text="error('due_at')"></p>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end">
                <a href="{{ $editing ? route('app.requests.show', $requestId) : route('app.requests.mine') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-secondary" :disabled="saving">{{ $editing ? 'Save changes' : 'Save as draft' }}</button>
                <button type="button" class="btn btn-primary" :disabled="saving" @click="save(true)" x-show="{{ $editing ? 'false' : 'true' }}">
                    <x-icon name="sparkles" /> <span x-text="saving ? 'Submitting…' : 'Create and submit'"></span>
                </button>
            </div>
        </form>

        <div class="card grid gap-3 p-5" x-show="loading" aria-hidden="true">
            <div class="skeleton h-4 w-1/3"></div><div class="skeleton h-9 w-full"></div><div class="skeleton h-32 w-full"></div>
        </div>

        <aside class="grid content-start gap-4">
            <div class="card p-4">
                <p class="eyebrow">What happens next</p>
                <ol class="mt-3 grid gap-3 text-sm">
                    <li class="flex gap-3"><span class="mono-id mt-0.5">01</span><span>Drafts stay private to you and can be edited.</span></li>
                    <li class="flex gap-3"><span class="mono-id mt-0.5">02</span><span>Submitting queues an AI analysis on a background worker.</span></li>
                    <li class="flex gap-3"><span class="mono-id mt-0.5">03</span><span>A manager reviews it, with the AI recommendation as advice only.</span></li>
                </ol>
            </div>
        </aside>
    </div>
</x-layouts.app>
