<?php

namespace App\Command;

use App\Service\WhatsAppTemplateSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-template',
    description: 'Değişkenli (parametreli) onaylı bir WhatsApp template mesajı gönderir.',
)]
class SendTemplateCommand extends Command
{
    public function __construct(
        private readonly WhatsAppTemplateSender $templateSender,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Alıcı numarası, ülke koduyla ve başında + olmadan (örn. 905551112233)')
            ->addArgument('template', InputArgument::REQUIRED, 'Onaylı template adı (örn. sinav_hatirlatma_v1)')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Şablonun dil kodu', 'tr')
            ->addOption(
                'param',
                'p',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Template body\'sindeki {{1}}, {{2}}, ... yerine sırayla basılacak değer (birden fazla kez kullanılabilir)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $to = $input->getArgument('to');
        $template = $input->getArgument('template');
        $lang = $input->getOption('lang');
        $params = $input->getOption('param');

        try {
            $io->note(sprintf(
                '"%s" şablonu %s numarasına gönderiliyor (parametreler: %s)...',
                $template,
                $to,
                $params === [] ? '(yok)' : implode(', ', $params)
            ));

            $result = $this->templateSender->sendTemplateMessage($to, $template, $lang, $params);

            if (isset($result['error'])) {
                $io->error('Gönderim reddedildi: ' . ($result['error']['message'] ?? json_encode($result['error'], JSON_UNESCAPED_UNICODE)));

                return Command::FAILURE;
            }

            $io->success('İstek başarıyla gönderildi.');
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('İşlem başarısız: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
