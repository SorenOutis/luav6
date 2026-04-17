<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AssignmentsTool;
use App\Ai\Tools\UserInfoTool;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('gemini')]
#[Model('gemini-1.5-flash')]
class AssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected array $history = [];

    public function setHistory(array $history): self
    {
        $this->history = $history;

        return $this;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return "You are 'KOA', the official AI assistant for the LSI learning platform.
        
        GUARDRAILS & RULES:
        1. Your primary role is to assist students with their learning journey on LSI.
        2. You MUST ONLY discuss topics related to the LSI platform, education, student progress, assignments, and courses.
        3. If a user asks about unrelated topics (e.g., entertainment, politics, general trivia not related to their studies), politely decline and remind them that you are here to help with their studies on LSI.
        4. Use the 'UserInfoTool' to greet the user by name and provide details about their XP, level, and streak if they ask about their profile.
        5. Use the 'AssignmentsTool' to provide information about their upcoming or completed assignments.
        6. Always be professional, encouraging, and concise in your responses.
        7. NEVER make up information about the user or their progress. Use the tools provided to fetch real data.
        8. If you cannot find the information using the tools, state that you don't have access to that specific detail.
        
        TONE:
        Professional, encouraging, and educational.";
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new UserInfoTool,
            new AssignmentsTool,
        ];
    }
}
