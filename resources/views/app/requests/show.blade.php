<x-layouts.app :title="'Request #'.$requestId">
    <div x-data="requestDetail(@js($requestId))" class="grid gap-6">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('app.requests.mine') }}" class="inline-flex w-fit items-center gap-1 text-sm text-muted hover:text-ink">
            <x-icon name="chevron-left" /> Back
        </a>

        <template x-if="loadError">
            @include('partials.error-banner', ['message' => 'loadError'])
        </template>

        {{-- Loading skeleton --}}
        <div class="grid gap-4" x-show="loading" aria-hidden="true">
            <div class="skeleton h-8 w-2/3"></div>
            <div class="skeleton h-4 w-1/3"></div>
            <div class="skeleton h-40 w-full"></div>
        </div>

        <template x-if="request">
            <div class="grid gap-6">
                {{-- Header --}}
                <header class="grid gap-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="mono-id" x-text="`#${request.id}`"></span>
                                <span class="pill" :class="$fmt.statusTone(request.status)" x-text="$fmt.status(request.status)"></span>
                                <span class="text-xs font-medium uppercase tracking-wide" :class="$fmt.priorityTone(request.priority)" x-text="`${$fmt.priority(request.priority)} priority`"></span>
                            </div>
                            <h1 class="mt-2 text-2xl font-semibold tracking-tight" x-text="request.title"></h1>
                            <p class="mt-1 text-sm text-muted">
                                <span x-text="$fmt.category(request.category)"></span> ·
                                Opened by <span class="text-ink-soft" x-text="request.creator?.name"></span>
                                <span x-text="$fmt.ago(request.created_at)"></span>
                            </p>
                        </div>

                        {{-- Workflow actions --}}
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Workflow actions">
                            <template x-if="isOwner && request.status === 'draft'">
                                <a :href="`/app/requests/${request.id}/edit`" class="btn btn-secondary"><x-icon name="pencil" /> Edit</a>
                            </template>
                            <template x-if="canAssign">
                                <button type="button" class="btn btn-secondary" @click="openAssign()"><x-icon name="user-plus" /> <span x-text="request.assignee ? 'Reassign' : 'Assign'"></span></button>
                            </template>
                            <template x-for="action in availableActions" :key="action.key">
                                <button type="button" class="btn" :class="action.tone === 'danger' ? 'btn-secondary text-danger' : 'btn-primary'"
                                        :disabled="busy !== null" @click="run(action)">
                                    <span x-text="busy === action.key ? 'Working…' : action.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Lifecycle stepper --}}
                    <ol class="card flex overflow-x-auto p-1" aria-label="Request lifecycle">
                        <template x-for="(item, index) in steps" :key="item.step">
                            <li class="flex min-w-max flex-1 items-center gap-2 rounded-(--radius-control) px-3 py-2 text-[13px]"
                                :class="{
                                    'bg-primary-soft text-primary-ink font-medium': item.state === 'current',
                                    'bg-danger-soft text-danger font-medium': item.state === 'rejected',
                                    'text-ink-soft': item.state === 'done',
                                    'text-muted': item.state === 'upcoming' || item.state === 'skipped',
                                }"
                                :aria-current="['current', 'rejected'].includes(item.state) ? 'step' : null">
                                <span class="grid size-5 place-items-center rounded-full border text-[10px] font-semibold"
                                      :class="item.state === 'done' ? 'border-ok bg-ok text-white' : item.state === 'upcoming' || item.state === 'skipped' ? 'border-line-strong' : 'border-current'">
                                    <template x-if="item.state === 'done'"><x-icon name="check" class="size-3" /></template>
                                    <template x-if="item.state !== 'done'"><span x-text="index + 1"></span></template>
                                </span>
                                <span x-text="item.state === 'rejected' ? 'Rejected' : $fmt.status(item.step)" :class="item.state === 'skipped' ? 'line-through' : ''"></span>
                            </li>
                        </template>
                    </ol>
                </header>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="grid content-start gap-6">
                        {{-- Description --}}
                        <section class="card" aria-labelledby="description-title">
                            <div class="card-header"><h2 id="description-title" class="card-title">Description</h2></div>
                            <p class="p-4 text-sm leading-6 whitespace-pre-line text-ink-soft" x-text="request.description"></p>
                        </section>

                        @include('partials.ai-panel')

                        {{-- Comments --}}
                        <section class="card" aria-labelledby="comments-title">
                            <div class="card-header">
                                <h2 id="comments-title" class="card-title flex items-center gap-2"><x-icon name="message" class="text-muted" /> Comments</h2>
                                <span class="eyebrow" x-text="comments.length"></span>
                            </div>
                            <ul class="divide-y divide-line">
                                <template x-for="item in comments" :key="item.id">
                                    <li class="flex gap-3 p-4">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-subtle text-[11px] font-semibold text-ink-soft" x-text="$fmt.initials(item.author?.name)" aria-hidden="true"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm">
                                                <span class="font-medium" x-text="item.author?.name"></span>
                                                <span class="eyebrow ml-1" x-text="item.author?.role"></span>
                                                <time class="ml-1 text-xs text-muted" :datetime="item.created_at" x-text="$fmt.ago(item.created_at)"></time>
                                            </p>
                                            <p class="mt-1 text-sm whitespace-pre-line text-ink-soft" x-text="item.body"></p>
                                        </div>
                                    </li>
                                </template>
                                <template x-if="!comments.length">
                                    <li class="p-4 text-sm text-muted">No comments yet. Add context for reviewers below.</li>
                                </template>
                            </ul>
                            <form class="grid gap-2 border-t border-line p-4" @submit.prevent="addComment()">
                                <label for="comment-body" class="sr-only">Add a comment</label>
                                <textarea id="comment-body" rows="3" class="input" maxlength="5000" placeholder="Add a comment" x-model="comment.body"
                                          :aria-invalid="comment.error ? 'true' : 'false'" aria-describedby="comment-error"
                                          @keydown.ctrl.enter="addComment()" @keydown.meta.enter="addComment()"></textarea>
                                <div class="flex items-center justify-between gap-3">
                                    <p id="comment-error" class="field-error mt-0" x-show="comment.error" x-text="comment.error"></p>
                                    <button type="submit" class="btn btn-primary btn-sm ml-auto" :disabled="comment.saving || !comment.body.trim()">
                                        <span x-text="comment.saving ? 'Posting…' : 'Post comment'"></span>
                                    </button>
                                </div>
                            </form>
                        </section>

                        {{-- Activity (operations only) --}}
                        <template x-if="ops">
                            <section class="card" aria-labelledby="activity-title">
                                <div class="card-header">
                                    <h2 id="activity-title" class="card-title flex items-center gap-2"><x-icon name="activity" class="text-muted" /> Activity</h2>
                                    <span class="eyebrow">Audit log</span>
                                </div>
                                <div class="p-4">
                                    @include('partials.activity-timeline', ['groups' => 'activityGroups'])
                                </div>
                            </section>
                        </template>
                    </div>

                    {{-- Sidebar: details, assignment, history --}}
                    <aside class="grid content-start gap-6">
                        <section class="card" aria-labelledby="details-title">
                            <div class="card-header"><h2 id="details-title" class="card-title">Details</h2></div>
                            <dl class="grid gap-3 p-4 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-muted">Priority</dt>
                                    <dd>
                                        <template x-if="ops && request.allowed_transitions.length">
                                            <div>
                                                <label for="priority-select" class="sr-only">Change priority</label>
                                                <select id="priority-select" class="input py-1 text-[13px]" :value="request.priority" @change="changePriority($event.target.value)">
                                                    @foreach (\App\Enums\RequestPriority::cases() as $case)
                                                        <option value="{{ $case->value }}">{{ ucfirst($case->value) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </template>
                                        <template x-if="!(ops && request.allowed_transitions.length)">
                                            <span class="font-medium" :class="$fmt.priorityTone(request.priority)" x-text="$fmt.priority(request.priority)"></span>
                                        </template>
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3"><dt class="text-muted">Assignee</dt><dd class="text-right" x-text="request.assignee?.name ?? 'Unassigned'"></dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted">Due</dt><dd class="text-right" x-text="$fmt.dateTime(request.due_at)"></dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted">Submitted</dt><dd class="text-right" x-text="$fmt.dateTime(request.submitted_at)"></dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted">Completed</dt><dd class="text-right" x-text="$fmt.dateTime(request.completed_at)"></dd></div>
                            </dl>
                        </section>

                        <section class="card" aria-labelledby="history-title">
                            <div class="card-header"><h2 id="history-title" class="card-title">Status history</h2></div>
                            <ol class="grid gap-3 p-4">
                                <template x-for="entry in history" :key="entry.id">
                                    <li class="grid gap-0.5 border-l-2 border-line pl-3 text-sm">
                                        <span><span class="text-muted" x-text="$fmt.status(entry.from_status)"></span> → <span class="font-medium" x-text="$fmt.status(entry.to_status)"></span></span>
                                        <span class="text-xs text-muted" x-text="`${entry.changed_by?.name ?? 'Unknown'} · ${$fmt.dateTime(entry.created_at)}`"></span>
                                        <template x-if="entry.reason"><span class="text-[13px] text-ink-soft italic" x-text="`“${entry.reason}”`"></span></template>
                                    </li>
                                </template>
                                <template x-if="!history.length"><li class="text-sm text-muted">Still a draft. History starts at submission.</li></template>
                            </ol>
                        </section>

                        <template x-if="request.assignments?.length">
                            <section class="card" aria-labelledby="assignments-title">
                                <div class="card-header"><h2 id="assignments-title" class="card-title">Assignment log</h2></div>
                                <ol class="grid gap-3 p-4 text-sm">
                                    <template x-for="item in request.assignments" :key="item.id">
                                        <li>
                                            <span class="font-medium" x-text="item.assignee?.name"></span>
                                            <span class="text-muted" x-text="`by ${item.assigned_by?.name} · ${$fmt.ago(item.created_at)}`"></span>
                                            <template x-if="item.note"><span class="block text-[13px] text-ink-soft" x-text="item.note"></span></template>
                                        </li>
                                    </template>
                                </ol>
                            </section>
                        </template>
                    </aside>
                </div>
            </div>
        </template>

        {{-- Assign dialog --}}
        <dialog id="assign-dialog" class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-(--radius-card) border border-line bg-surface p-0 text-ink shadow-(--shadow-pop) backdrop:bg-ink/40" aria-labelledby="assign-title">
            <form class="grid gap-4 p-5" @submit.prevent="submitAssign()">
                <div>
                    <h2 id="assign-title" class="text-base font-semibold">Assign request</h2>
                    <p class="mt-1 text-sm text-muted">Only managers and admins can own operational work.</p>
                </div>
                <div>
                    <label for="assignee" class="field-label">Assignee</label>
                    <select id="assignee" class="input" required x-model="assign.assignee_id">
                        <option value="" disabled>Choose a person</option>
                        <template x-for="person in staff" :key="person.id">
                            <option :value="person.id" x-text="`${person.name} (${person.role})`" :selected="Number(assign.assignee_id) === person.id"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label for="assign-note" class="field-label">Note <span class="font-normal text-muted">(optional)</span></label>
                    <input id="assign-note" type="text" class="input" maxlength="500" x-model="assign.note" placeholder="e.g. Platform on-call this week">
                </div>
                <p class="field-error mt-0" role="alert" x-show="assign.error" x-text="assign.error"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="$el.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="assign.saving || !assign.assignee_id" x-text="assign.saving ? 'Assigning…' : 'Assign'"></button>
                </div>
            </form>
        </dialog>
    </div>
</x-layouts.app>
