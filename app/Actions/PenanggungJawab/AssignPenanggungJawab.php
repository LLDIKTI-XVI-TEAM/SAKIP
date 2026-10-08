<?php

namespace App\Actions\PenanggungJawab;

use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\User;
use App\Services\PenanggungJawab\AppendAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AssignPenanggungJawab
{
    public function __construct(private readonly AppendAssignment $assignments) {}

    /**
     * @param  array{user_id:string,tanggal_mulai_berlaku:string,expected_state:string,alasan?:?string}  $data
     * @return array{assignment:PenugasanIndikator,warning:?string}
     */
    public function handle(User $actor, IndikatorKinerja $indicator, array $data): array
    {
        try {
            $result = DB::transaction(fn () => $this->assignments->append($actor, $indicator, $data, true));
        } catch (QueryException $error) {
            $result = $this->assignments->uniqueConflict($error, $actor);
        }

        return $this->assignments->finish($actor, $indicator, $result);
    }
}
