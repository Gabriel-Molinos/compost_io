<?php

declare(strict_types=1);

namespace App\Support;

/**
 * O que o Pixelito responde quando o redator pergunta "como faço X?" (painel do
 * canto inferior direito, layout/_pixelito.php). Perguntas e respostas escritas à
 * mão — sem IA, sem custo, sempre a mesma resposta certa. Cada resposta tem, se
 * fizer sentido, um link direto pra tela onde a coisa acontece.
 *
 * Pra ADICIONAR uma dúvida: acrescente um item em `topics()`. `{site}` no link vira
 * o id do site que o usuário está vendo (ou o primeiro site dele); sem nenhum site
 * o link cai em /sites. O teste PixelitoGuideTest garante que todo link aponta pra
 * uma rota que existe. Mantenha as respostas curtas (2–3 frases) e fiéis à tela.
 */
final class PixelitoGuide
{
    /** Primeira fala do Pixelito quando o painel abre (digitada na hora, como num chat). */
    public const GREETING = 'Oi, eu sou o Pixelito! Toque numa dúvida aí embaixo (ou digite palavras-chave) que eu te explico.';

    /** Resposta quando o que a pessoa digitou não bate com nenhuma pergunta do guia. */
    public const NOT_FOUND = 'Hmm, não achei essa dúvida por aqui. Conta pra equipe o que faltou que a gente coloca no guia!';

    public const NOT_FOUND_LINK = '/feedback';
    public const NOT_FOUND_LINK_LABEL = 'Falar com a equipe';

    /**
     * @return list<array{topic: string, items: list<array{q: string, a: string, link: ?string, linkLabel: ?string}>}>
     */
    public static function topics(?int $siteId): array
    {
        $topics = [
            [
                'topic' => 'Metas, categorias e interesses',
                'items' => [
                    [
                        'q' => 'Como crio uma meta do mês?',
                        'a' => 'Abra Metas no seu site, crie uma nova, escolha o mês, o total de artigos e, se quiser, quantos por categoria. Sem meta no mês atual nem no próximo, a geração automática fica pausada e eu te aviso.',
                        'link' => '/sites/{site}/goals/new', 'linkLabel' => 'Criar uma meta',
                    ],
                    [
                        'q' => 'Para que servem as categorias e as diretrizes?',
                        'a' => 'Cada categoria pode ter diretrizes: o que a IA deve seguir quando escrever naquele assunto. Se o site está ligado ao WordPress, um administrador pode importar as categorias de lá.',
                        'link' => '/sites/{site}/categories', 'linkLabel' => 'Ver categorias',
                    ],
                    [
                        'q' => 'O que são os Interesses?',
                        'a' => 'Guiam a IA na escolha da pauta: interesses puxam o texto para certos temas e não-interesses evitam outros. Cada regra tem intensidade de 1 (leve) a 5 (forte).',
                        'link' => '/sites/{site}/rules', 'linkLabel' => 'Ver interesses',
                    ],
                ],
            ],
            [
                'topic' => 'Gerar rascunhos',
                'items' => [
                    [
                        'q' => 'Como gero um rascunho?',
                        'a' => 'Em Produção, use "Gerar novo rascunho": escolha a meta e a categoria (ou deixe a IA decidir) e clique em Gerar rascunho. Leva alguns minutos e cada geração tem custo de IA. Quando ficar pronto, eu aviso.',
                        'link' => '/sites/{site}/production', 'linkLabel' => 'Abrir Produção',
                    ],
                    [
                        'q' => 'Quero um post específico, do jeito que eu descrever',
                        'a' => 'Em Produção, clique em "Rascunho específico": escolha a categoria e descreva o post (assunto, para quem, pontos obrigatórios, tom, o que evitar — mínimo de 40 caracteres). A IA segue o seu pedido à risca; só as regras de compliance continuam valendo por cima.',
                        'link' => '/sites/{site}/production', 'linkLabel' => 'Abrir Produção',
                    ],
                    [
                        'q' => 'A IA gera rascunhos sozinha?',
                        'a' => 'Sim: todo dia, 1 rascunho por site ativo, seguindo a meta do mês (ou a do próximo mês, se o atual não tiver). Sem meta nenhuma, a geração pausa e a equipe recebe um aviso. Esses rascunhos têm a etiqueta "automático".',
                        'link' => '/sites/{site}/goals', 'linkLabel' => 'Ver metas',
                    ],
                ],
            ],
            [
                'topic' => 'Revisar e aprovar',
                'items' => [
                    [
                        'q' => 'Como aprovo ou rejeito um artigo?',
                        'a' => 'Abra o rascunho em revisão, leia o texto e confira o checklist de qualidade. "Aprovar" libera para agendar e publicar. "Rejeitar" pede o motivo e a justificativa, e a IA escreve de novo com base nisso — até 3 tentativas antes de bloquear.',
                        'link' => '/sites/{site}/production', 'linkLabel' => 'Ver rascunhos',
                    ],
                    [
                        'q' => 'Posso corrigir o texto eu mesmo?',
                        'a' => 'Pode, enquanto o artigo está em revisão: edite o corpo e clique em "Salvar corpo". Depois de aprovado, o texto fica travado.',
                        'link' => null, 'linkLabel' => null,
                    ],
                    [
                        'q' => 'Como envio uma imagem minha?',
                        'a' => 'No artigo, na área de imagens, use "Enviar uma imagem sua" e escolha se ela vai como destacada ou no corpo. Precisa ser WebP, com largura de 1200 a 2560 px, proporção 16:9 e até 2 MB — se não servir, eu explico o motivo.',
                        'link' => null, 'linkLabel' => null,
                    ],
                ],
            ],
            [
                'topic' => 'Agendar e publicar',
                'items' => [
                    [
                        'q' => 'Como agendo e publico um artigo aprovado?',
                        'a' => 'No artigo aprovado, escolha a imagem destacada e use "Agendar publicação" (autor, data e hora): o COMPOST envia ao WordPress sozinho no horário marcado. Se quiser antes, use "Publicar no WordPress agora". Acompanhe no Calendário — dá para arrastar um card e reagendar.',
                        'link' => '/sites/{site}/calendar', 'linkLabel' => 'Abrir o Calendário',
                    ],
                    [
                        'q' => 'Como retiro um post já publicado?',
                        'a' => 'No artigo publicado, use "Retirar do WordPress": o post vai para a lixeira do WordPress e o artigo volta para "aprovado", pronto para agendar de novo.',
                        'link' => null, 'linkLabel' => null,
                    ],
                    [
                        'q' => 'O post não foi para o WordPress. E agora?',
                        'a' => 'A conexão com o WordPress é configurada por um administrador. Quando um envio falha, você recebe um aviso com o motivo — repasse para um admin testar a conexão do site.',
                        'link' => '/feedback', 'linkLabel' => 'Falar com a equipe',
                    ],
                ],
            ],
            [
                'topic' => 'Avisos e regras',
                'items' => [
                    [
                        'q' => 'Onde vejo os meus avisos?',
                        'a' => 'No sino da barra lateral e na página de Notificações. Quando chega um aviso novo, eu apareço num pop-up. Dá para marcar como lida e filtrar por tipo.',
                        'link' => '/notifications', 'linkLabel' => 'Abrir Notificações',
                    ],
                    [
                        'q' => 'Quais regras a IA precisa seguir?',
                        'a' => 'As regras de compliance valem para todo artigo (por exemplo, mínimo de 1500 palavras, links e imagens). Vale conhecer antes de aprovar um texto.',
                        'link' => '/compliance', 'linkLabel' => 'Ver as regras',
                    ],
                    [
                        'q' => 'Achei um problema ou tenho uma sugestão',
                        'a' => 'Conta pra equipe em Feedback — a mensagem chega direto para os administradores.',
                        'link' => '/feedback', 'linkLabel' => 'Enviar feedback',
                    ],
                ],
            ],
        ];

        // {site} → id do site; sem nenhum site, o link cai na lista de sites (nunca um link quebrado).
        foreach ($topics as &$topic) {
            foreach ($topic['items'] as &$item) {
                if ($item['link'] !== null && str_contains($item['link'], '{site}')) {
                    if ($siteId === null) {
                        $item['link'] = '/sites';
                        $item['linkLabel'] = 'Ver meus sites';
                    } else {
                        $item['link'] = str_replace('{site}', (string) $siteId, $item['link']);
                    }
                }
            }
            unset($item);
        }
        unset($topic);

        return $topics;
    }
}
