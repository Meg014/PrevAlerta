<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AlertSummaryService;
use Cake\Http\Response;

class DesktopController extends AppController
{
    /** Consulta autenticada pelo mesmo mecanismo das telas do servidor. */
    public function summary(): Response
    {
        $this->request->allowMethod(['get']);

        return $this->response->withType('json')->withHeader('Cache-Control', 'no-store')
            ->withStringBody(json_encode((new AlertSummaryService())->read(), JSON_THROW_ON_ERROR));
    }
}
