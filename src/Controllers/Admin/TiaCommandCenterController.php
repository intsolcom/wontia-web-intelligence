<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\CiuService;

class TiaCommandCenterController
{
    private function service(): CiuService
    {
        return new CiuService();
    }

    public function overview(): void
    {
        Response::json($this->service()->overview());
    }

    public function ensure(): void
    {
        $this->service()->ensureTables();
        Response::json(['ok' => true, 'message' => 'Tablas CIU listas.']);
    }

    public function sync(): void
    {
        $result = $this->service()->syncAll();
        Response::json($result, !empty($result['ok']) ? 200 : 207);
    }

    public function search(Request $req): void
    {
        Response::json(['ok' => true, 'data' => $this->service()->search((string)$req->get('q', ''), (int)$req->get('limit', 8))]);
    }

    public function document(Request $req, string $id): void
    {
        $doc = $this->service()->document((int)$id);
        Response::json($doc ? ['ok' => true, 'data' => $doc] : ['ok' => false, 'message' => 'Documento no encontrado.'], $doc ? 200 : 404);
    }

    public function questions(Request $req): void
    {
        $unmatched = (string)$req->get('unmatched', '0') === '1';
        Response::json(['ok' => true, 'data' => $this->service()->questions((int)$req->get('limit', 50), $unmatched)]);
    }

    public function training(): void
    {
        $training = (new \App\Services\TiaTrainingService())->overview();
        $interactions = new \App\Services\TiaInteractionService();
        $catalog = new \App\Services\TiaAttentionCatalogService();
        $training['rules'] = $interactions->rules(20);
        $training['cases'] = $interactions->pendingInsights(10);
        $training['attention_stats'] = $catalog->stats();
        $training['attention_preview'] = $catalog->search('', '', 12);
        Response::json($training);
    }

    public function attention(Request $req): void
    {
        $catalog = new \App\Services\TiaAttentionCatalogService();
        Response::json([
            'ok' => true,
            'data' => $catalog->search((string)$req->get('q', ''), (string)$req->get('category', ''), (int)$req->get('limit', 80)),
            'stats' => $catalog->stats(),
        ]);
    }

    public function simulation(Request $req): void
    {
        Response::json((new \App\Services\TiaSimulationService())->overview());
    }

    public function simulationStart(Request $req): void
    {
        $user = \App\Core\Session::user() ?: [];
        Response::json((new \App\Services\TiaSimulationService())->start((string)($user['username'] ?? 'admin')));
    }

    public function simulationStep(Request $req, string $id): void
    {
        Response::json((new \App\Services\TiaSimulationService())->step((int)$id));
    }

    public function simulationApplyPrompt(Request $req): void
    {
        Response::json((new \App\Services\TiaSimulationService())->applyMasterPrompt());
    }

    public function learning(Request $req): void
    {
        Response::json((new \App\Services\TiaLearningService())->overview());
    }

    public function learningUrl(Request $req): void
    {
        $user = \App\Core\Session::user() ?: [];
        Response::json((new \App\Services\TiaLearningService())->ingestUrl((string)$req->input('url', ''), (string)$req->input('title', ''), (string)($user['username'] ?? 'admin')));
    }

    public function learningUpload(Request $req): void
    {
        $user = \App\Core\Session::user() ?: [];
        $file = is_array($_FILES['file'] ?? null) ? $_FILES['file'] : [];
        Response::json((new \App\Services\TiaLearningService())->ingestFile($file, (string)($user['username'] ?? 'admin')));
    }

    public function learningCommand(Request $req): void
    {
        $user = \App\Core\Session::user() ?: [];
        Response::json((new \App\Services\TiaLearningService())->addCommand((string)$req->input('bad', ''), (string)$req->input('good', ''), (string)($user['username'] ?? 'admin')));
    }

    public function learningSourceDelete(Request $req, string $id): void
    {
        Response::json((new \App\Services\TiaLearningService())->deleteSource((int)$id));
    }

    public function learningRunStart(Request $req): void
    {
        $user = \App\Core\Session::user() ?: [];
        Response::json((new \App\Services\TiaLearningRunService())->start((string)($user['username'] ?? 'admin')));
    }

    public function learningRunStep(Request $req, string $id): void
    {
        Response::json((new \App\Services\TiaLearningRunService())->step((int)$id));
    }

    public function learningRunShow(Request $req, string $id): void
    {
        Response::json((new \App\Services\TiaLearningRunService())->job((int)$id));
    }

    public function profit(Request $req): void
    {
        Response::json((new \App\Services\TiaProfitService())->overview((string)$req->get('period', '')));
    }

    public function profitPolicy(Request $req): void
    {
        Response::json((new \App\Services\TiaProfitService())->savePolicy($req->json()));
    }

    public function profitCostAdd(Request $req): void
    {
        Response::json((new \App\Services\TiaProfitService())->addCost($req->json()));
    }

    public function profitCostDelete(Request $req, string $id): void
    {
        Response::json((new \App\Services\TiaProfitService())->deleteCost((int)$id));
    }

    public function profitProductCostAdd(Request $req): void
    {
        Response::json((new \App\Services\TiaProfitService())->addProductCost($req->json()));
    }

    public function profitProductCostDelete(Request $req, string $id): void
    {
        Response::json((new \App\Services\TiaProfitService())->deleteProductCost((int)$id));
    }

    public function profitCampaignAdd(Request $req): void
    {
        Response::json((new \App\Services\TiaProfitService())->addCampaign($req->json()));
    }

    public function profitCampaignDelete(Request $req, string $id): void
    {
        Response::json((new \App\Services\TiaProfitService())->deleteCampaign((int)$id));
    }

    public function saveRole(Request $req): void
    {
        $result = (new \App\Services\TiaTrainingService())->saveRole($req->json(), (string)($_SESSION['user']['username'] ?? 'admin'));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function saveIntent(Request $req): void
    {
        $result = (new \App\Services\TiaTrainingService())->saveIntent($req->json(), (string)($_SESSION['user']['username'] ?? 'admin'));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function saveSentiment(Request $req): void
    {
        $result = (new \App\Services\TiaTrainingService())->saveSentiment($req->json(), (string)($_SESSION['user']['username'] ?? 'admin'));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function saveObjection(Request $req): void
    {
        $result = (new \App\Services\TiaTrainingService())->saveObjection($req->json(), (string)($_SESSION['user']['username'] ?? 'admin'));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function simulate(Request $req): void
    {
        Response::json((new \App\Services\TiaTrainingService())->simulate((string)$req->input('message', ''), (string)$req->input('page', '')));
    }

    public function interactions(Request $req): void
    {
        Response::json((new \App\Services\TiaInteractionService())->overview((int)$req->get('days', 30)));
    }

    public function analyzeInteractions(Request $req): void
    {
        $result = (new \App\Services\TiaInteractionService())->analyze((int)$req->input('days', 30));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function applyInsight(Request $req, string $id): void
    {
        $user = \App\Core\Session::user() ?: [];
        $result = (new \App\Services\TiaInteractionService())->applyInsight((int)$id, (string)($user['username'] ?? 'admin'));
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }

    public function toggleExperiment(Request $req, string $id): void
    {
        $status = (string)$req->input('status', 'paused');
        $result = (new \App\Services\TiaExperimentService())->toggle((int)$id, $status);
        Response::json($result, !empty($result['ok']) ? 200 : 422);
    }
}
