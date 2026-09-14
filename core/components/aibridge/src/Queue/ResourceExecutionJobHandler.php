<?php

declare(strict_types=1);

namespace AIBridge\Queue;

use AIBridge\Execution\ResourceExecutionService;
use MODX\Revolution\modX;
use AIBridge\Workflow\ChangeState;
use AIBridge\Workflow\ChangeRequestService;
use AIBridge\Verification\VerificationService;

final class ResourceExecutionJobHandler implements JobHandler
{
    public function __construct(private readonly modX $modx) {}

    public function handle(JobRecord $job, JobContext $context): array
    {
        $payload = $job->payload();
        $operation = (string) ($payload['operation'] ?? '');
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $principal = is_array($payload['principal'] ?? null) ? $payload['principal'] : [];
        $request = is_array($payload['request'] ?? null) ? $payload['request'] : [];
        $request['request_id'] ??= $job->raw()['request_id'] ?? null;
        $deadline = $context->deadline();
        if ($deadline !== null) {
            $request['_deadline_at'] = $deadline->at();
        }
        $context->ensureWithinDeadline();
        $context->progress(10, ['phase' => 'execution_started']);
        $service = new ResourceExecutionService($this->modx);
        $result = match ($operation) {
            'resource.create' => $service->create($input, $principal, $request),
            'resource.update' => $service->update($input, $principal, $request),
            'resource.delete' => $service->delete($input, $principal, $request),
            'resource.publish' => $service->publish($input, $principal, $request),
            'resource.preview' => $service->preview($input, $principal, $request),
            default => throw new NonRetryableJobException('Unsupported resource operation: ' . $operation),
        };
        $changeId=(int)($request['change_id']??0);
        if($changeId>0){
            $change=$this->modx->getObject(\AIBridge\Model\ChangeRequest::class,$changeId);
            if($change){
                $verified=false;
                if(($result['success']??false)===true){
                    $resourceId=(int)($result['data']['resource_id']??0);
                    if($resourceId>0 && !(int)($change->get('resource_id'))){ $change->set('resource_id',$resourceId); }
                    $operation=(string)$change->get('operation');
                    $verification=$operation==='resource.delete'
                        ? (new VerificationService($this->modx))->verifyDeleted((int)$change->get('resource_id'))
                        : (new VerificationService($this->modx))->verifyChange($change->toArray());
                    $verified=$verification->toArray()['valid'];
                    $result['verification']=$verification->toArray();
                }
                $change->set('status',(($result['success']??false) && $verified)?ChangeState::COMPLETED:ChangeState::FAILED);
                $change->set('updated_at',gmdate('Y-m-d H:i:s')); $change->save();
                if(!$verified && ($result['success']??false)) throw new NonRetryableJobException('Post-execution verification failed.');
            }
        }
        if(($result['error']['code']??'')==='execution_timeout'){
            // A deadline abort that slipped past the pre-dispatch check is a
            // terminal timeout, not a completed job.
            throw new JobTimeoutException((string)($result['error']['message']??'Job deadline exceeded.'));
        }
        $context->progress(100, ['phase' => 'execution_completed']);
        return $result;
    }
}
