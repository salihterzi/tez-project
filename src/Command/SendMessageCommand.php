<?php

namespace App\Command;

use App\Service\MessageGeneratorService;
use App\Service\WhatsAppClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-message',
    description: 'Öğrenciye WhatsApp üzerinden, isteğe bağlı olarak OpenAI ile üretilmiş bir mesaj gönderir.',
)]
class SendMessageCommand extends Command
{
    public function __construct(
        private readonly WhatsAppClient $whatsAppClient,
        private readonly MessageGeneratorService $messageGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Alıcı numarası, ülke koduyla ve başında + olmadan (örn. 905551112233)')
            ->addArgument('message', InputArgument::REQUIRED, 'Gönderilecek mesaj metni, ya da --generate kullanılıyorsa AI için prompt')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Bir şablon adı verilirse "message" argümanı yerine bu şablon gönderilir (örn. hello_world)')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Şablonun dil kodu', 'en_US')
            ->addOption('generate', 'g', InputOption::VALUE_NONE, 'Verilirse, "message" argümanı gönderilecek metin değil, OpenAI için bir prompt olarak kullanılır')
            ->addOption('system-prompt', 's', InputOption::VALUE_REQUIRED, 'AI üretimi için sistem promptu / persona talimatı (yalnızca --generate ile birlikte)', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $to = $input->getArgument('to');
        $message = $input->getArgument('message');
        $template = $input->getOption('template');
        $lang = $input->getOption('lang');
        $shouldGenerate = $input->getOption('generate');
        $systemPrompt = $input->getOption('system-prompt');

        try {
            if ($template) {
                $io->note(sprintf('"%s" şablonu %s numarasına gönderiliyor...', $template, $to));
                $result = $this->whatsAppClient->sendTemplateMessage($to, $template, $lang);
                $io->success('İstek başarıyla gönderildi.');
                $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return Command::SUCCESS;
            }

            if ($shouldGenerate) {
                $io->note('OpenAI ile mesaj metni üretiliyor...');
                $message = $this->messageGenerator->generateMessage($message, $systemPrompt);
                $io->writeln('Üretilen mesaj: ' . $message);
            }

            $io->note(sprintf('Mesaj %s numarasına gönderiliyor...', $to));
            $result = $this->whatsAppClient->sendTextMessage($to, $message);

            $io->success('İstek başarıyla gönderildi.');
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('İşlem başarısız: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
