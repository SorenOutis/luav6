---
paths:
  - 'app/Ai/Gateway/**'
---

# Gateway

## Live 9router always replies with SSE; SSE recovery is load-bearing
The live openai-compatible provider (9router, ag/ models) always responds with an SSE chat-completion stream, even when stream:false is requested, and it ignores reasoning_effort / thinking params. The SSE reassembly in HeaderAwareOpenAiCompatibleGateway is load-bearing — do not remove. Provider options cannot control reasoning on this router; pick a model variant instead.
