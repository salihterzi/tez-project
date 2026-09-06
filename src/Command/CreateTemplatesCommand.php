<?php

namespace App\Command;

use App\Service\WhatsAppTemplateClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-templates',
    description: 'config/whatsapp/templates.json içindeki WhatsApp mesaj şablonlarını Meta\'ya toplu olarak yükler.',
)]
class CreateTemplatesCommand extends Command
{
    public function __construct(
        private readonly WhatsAppTemplateClient $templateClient,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'Template tanımlarının bulunduğu JSON dosyasının yolu',
                'config/whatsapp/templates.json'
            )
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Verilirse, dosyadaki tüm şablonlar yerine sadece bu isimdeki şablon gönderilir'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $relativePath = $input->getOption('file');
        $path = str_starts_with($relativePath, '/') ? $relativePath : $this->projectDir . '/' . $relativePath;

        if (!is_file($path)) {
            $io->error(sprintf('Dosya bulunamadı: %s', $path));

            return Command::FAILURE;
        }

        $raw = file_get_contents($path);
        $templates = json_decode($raw, true);

        if (!is_array($templates) || json_last_error() !== JSON_ERROR_NONE) {
            $io->error('JSON dosyası okunamadı/geçersiz: ' . json_last_error_msg());

            return Command::FAILURE;
        }

        $onlyName = $input->getOption('name');
        if ($onlyName !== null) {
            $templates = array_values(array_filter(
                $templates,
                fn (array $t) => ($t['name'] ?? null) === $onlyName
            ));

            if ($templates === []) {
                $io->error(sprintf('"%s" isimli şablon dosyada bulunamadı.', $onlyName));

                return Command::FAILURE;
            }
        }

        $io->note(sprintf('%d şablon bulundu, Meta\'ya gönderiliyor...', count($templates)));

        $successCount = 0;
        $failCount = 0;

        foreach ($templates as $index => $definition) {
            $name = $definition['name'] ?? sprintf('(isimsiz #%d)', $index);

            try {
                $result = $this->templateClient->createTemplate($definition);

                if (isset($result['error'])) {
                    $failCount++;
                    $io->error(sprintf(
                        '"%s" reddedildi: %s',
                        $name,
                        $result['error']['message'] ?? json_encode($result['error'], JSON_UNESCAPED_UNICODE)
                    ));
                    continue;
                }

                $successCount++;
                $io->writeln(sprintf(
                    '<info>✓</info> "%s" gönderildi (id: %s, status: %s)',
                    $name,
                    $result['id'] ?? '?',
                    $result['status'] ?? '?'
                ));
            } catch (\Throwable $e) {
                $failCount++;
                $io->error(sprintf('"%s" için istek başarısız: %s', $name, $e->getMessage()));
            }
        }

        $io->newLine();
        if ($failCount === 0) {
            $io->success(sprintf('%d şablon başarıyla gönderildi.', $successCount));

            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d başarılı, %d başarısız.', $successCount, $failCount));

        return $successCount > 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
