<?php

/**
 * Provides metadata about an eligibility check.
 */
class ResultInfo
{
    ##########
    ## Accessors
    ##########
    /**
     * The eligibility check result (one of the {@see Result} values).
     * @var int
     */
    public $result;

    /**
     * Whether the result is final (i.e. there's no need to check further wikis).
     * @var bool
     */
    public $isFinal = false;

    /**
     * A human-readable message summarising the eligibility result.
     * @var string
     */
    public $message;

    /**
     * Warning messages to append for this result.
     * @var string[]
     */
    public $warnings = [];

    /**
     * Notes to append for this result.
     * @var string[]
     */
    public $notes = [];


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param int $result The eligibility check result (one of the {@see Result} values).
     * @param string $message Whether the result is final (i.e. there's no need to check further wikis).
     * @param bool $isFinal A human-readable message summarising the eligibility result.
     */
    public function __construct($result, $message, $isFinal = true)
    {
        $this->result = $result;
        $this->message = $message;
        $this->isFinal = $isFinal;
    }

    /**
     * Get whether the eligibility check passed.
     * @var bool
     */
    public function isPass()
    {
        return $this->result == Result::PASS;
    }

    /**
     * Get whether the eligibility check failed.
     * @var bool
     */
    public function isFail()
    {
        return $this->result == Result::FAIL;
    }

    /**
     * Add a warning message for this result.
     * @param string $message The warning message.
     */
    public function addWarning($message)
    {
        array_push($this->warnings, $message);
    }

    /**
     * Add a note about this result.
     * @param string $message The note message.
     */
    public function addNote($message)
    {
        array_push($this->notes, $message);
    }
}
