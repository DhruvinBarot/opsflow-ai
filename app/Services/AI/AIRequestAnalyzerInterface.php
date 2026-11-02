<?php

namespace App\Services\AI;

use App\Models\OperationalRequest;

interface AIRequestAnalyzerInterface
{
    /**
     * Produce advisory triage metadata for a request.
     *
     * @throws AIProviderException when the provider cannot be reached or refuses the call
     * @throws InvalidAnalysisOutputException when the provider answers with unusable output
     */
    public function analyze(OperationalRequest $request): AnalysisResult;

    /**
     * Short provider identifier persisted with each analysis, e.g. "local" or "openai".
     */
    public function name(): string;
}
