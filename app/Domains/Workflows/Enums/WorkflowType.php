<?php

declare(strict_types=1);

namespace App\Domains\Workflows\Enums;

enum WorkflowType: string
{
    case ResultPublication = 'result_publication';
    case ExamPublication = 'exam_publication';
    
    // Add future workflow types here, e.g.:
    // case AnotherWorkflowType = 'another_workflow_type';
    }