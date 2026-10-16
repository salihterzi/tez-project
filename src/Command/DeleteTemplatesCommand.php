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
    name: 'app:delete-templates',
    description: 'config/whatsapp/templates.json içindeki WhatsApp mesaj şablonlarını Meta\'dan toplu olarak siler — içerik güncellenmiş bir şablonu aynı isimle yeniden oluşturabilmek için (Meta, APPROVED bir şablonun aynı isimle doğrudan güncellenmesini reddeder) önce bu komutla silinmesi, sonra app:create-templates ile yeniden gönderilmesi gerekir.',
)]
class DeleteTemplatesCommand extends Command
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
                'Verilirse, dosyadaki tüm şablonlar yerine sadece bu isimdeki şablon silinir'
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

        // Aynı isim (persona sonekli de olsa farklı name'ler olduğu için aslında zaten
        // benzersiz) birden fazla kez silinmesin — templates.json'da tekrar yok ama
        // ileride --name olmadan tüm dosya verilirse garanti olsun diye.
        $isimler = array_unique(array_map(static fn (array $t): string => $t['name'] ?? '', $templates));

        $io->note(sprintf('%d benzersiz şablon adı bulundu, Meta\'dan siliniyor...', count($isimler)));

        $successCount = 0;
        $failCount = 0;

        foreach ($isimler as $name) {
            if ('' === $name) {
                continue;
            }

            try {
                $result = $this->templateClient->deleteTemplate($name);

                if (isset($result['error'])) {
                    $failCount++;
                    $io->writeln(sprintf(
                        '<comment>✗</comment> "%s" silinemedi: %s',
                        $name,
                        $result['error']['message'] ?? json_encode($result['error'], JSON_UNESCAPED_UNICODE)
                    ));
                    continue;
                }

                $successCount++;
                $io->writeln(sprintf('<info>✓</info> "%s" silindi.', $name));
            } catch (\Throwable $e) {
                $failCount++;
                $io->writeln(sprintf('<comment>✗</comment> "%s" için istek başarısız: %s', $name, $e->getMessage()));
            }
        }

        $io->newLine();
        $io->warning(sprintf(
            '%d şablon silindi, %d başarısız (hiç oluşturulmamış olabilir — zararsız). Şimdi app:create-templates ile yeniden oluşturulabilir.',
            $successCount,
            $failCount,
        ));

        return Command::SUCCESS;
    }
}
