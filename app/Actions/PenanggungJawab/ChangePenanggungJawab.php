<?php

namespace App\Actions\PenanggungJawab;

use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\User;
use App\Services\PenanggungJawab\AppendAssignment;
use Illuminate\Support\Facades\DB;

class ChangePenanggungJawab
{
    public function __construct(private readonly AppendAssignment $assignments) {}

    /**
     * @param  array{user_id:string,tanggal_mulai_berlaku:string,expected_state:string,alasan?:?string}  $data
     * @return array{assignment:PenugasanIndikator,warning:?string}
     */
    public function handle(User $actor, IndikatorKinerja $indicator, array $data): array
    {
        $result = DB::transaction(fn () => $this->assignments->append($actor, $indicator, $data, false));

        return $this->assignments->finish($actor, $indicator, $result);
    }
}
