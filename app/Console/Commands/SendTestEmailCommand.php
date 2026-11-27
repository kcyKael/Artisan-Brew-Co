<?php

namespace App\Console\Commands;

use App\Services\Email\EmailTemplateRenderer;
use App\Services\Email\TransactionalEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendTestEmailCommand extends Command
{
    protected $signature = 'abc:emails-send-test {email : Recipient email address} {--template=receipt : Template key from EmailTemplateRenderer}';

    protected $description = 'Queue a test branded email through the Brevo outbox pipeline to an explicit test recipient';

    public function handle(TransactionalEmailService $transactional, EmailTemplateRenderer $renderer): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please supply a valid email address.');

            return self::FAILURE;
        }

        $template = (string) $this->option('template');
        if (! array_key_exists($template, EmailTemplateRenderer::TEMPLATES)) {
            $this->error("Unknown template '{$template}'.");

            return self::FAILURE;
        }

        $delivery = $transactional->queue(
            messageKey: 'cli-test:'.$template.':'.Str::uuid(),
            purpose: $template,
            recipientEmail: $email,
            recipientName: null,
            subject: '[Test] '.EmailTemplateRenderer::TEMPLATES[$template].' — Artisan Brew Co.',
            templateName: $template,
            templateData: $renderer->sampleContext($template),
        );

        $this->info("Queued delivery #{$delivery->id} (status: {$delivery->status}) for {$email}.");

        return self::SUCCESS;
    }
}
