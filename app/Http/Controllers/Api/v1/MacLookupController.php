<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Services\MacLookupService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\RegisterMacResource;
use Exception;

class MacLookupController extends Controller
{
    private MacLookupService $macLookupService;

    public function __construct(
        MacLookupService $macLookupService
    ) {
        $this->macLookupService = $macLookupService;
    }

    public function lookup(Request $request, string $mac): JsonResponse
    {
        try {
            $MACInfo = $this->macLookupService->getRegisterForSingleMac($mac);
            if (!$MACInfo) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(
                (new RegisterMacResource($MACInfo))
            );
        } catch (Exception $e) {
            return $this->errorResponse([],
                $e->getMessage(),
                $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function lookupList(Request $request): JsonResponse
    {
        try {
            /**
             * 00-11-22-00-11-A2 → Covered /^([0-9A-Fa-f]{2}[:-]?){5}([0-9A-Fa-f]{2})$/
             * 00:11:22:33:44:55 → Covered /^([0-9A-Fa-f]{2}[:-]?){5}([0-9A-Fa-f]{2})$/
             * 061122334455 → Covered /^([0-9A-Fa-f]{2}[:-]?){5}([0-9A-Fa-f]{2})$/
             * 0001.1122.2333 → Covered (Cisco-style MAC) [regex:/^([0-9A-Fa-f]{4}\.){2}[0-9A-Fa-f]{4}$/]
             */
            $validated = $request->validate([
                'MACAddressList' => ['required', 'array'],

                /* Without cisco mac address */
                //'MACAddressList.*' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]?){5}([0-9A-Fa-f]{2})$/'],

                /* With cisco style Mac address too */
                'MACAddressList.*' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]?){5}([0-9A-Fa-f]{2})$|^([0-9A-Fa-f]{4}\.){2}[0-9A-Fa-f]{4}$/'],
            ], [
                'MACAddressList.required' => 'You must provide a list of MAC addresses.',
                'MACAddressList.array' => 'The MACAddressList must be an array.',
                'MACAddressList.*.required' => 'Each MAC address is required.',
                'MACAddressList.*.string' => 'Each MAC address must be a string.',

                /* Without cisco mac address */
                //'MACAddressList.*.regex' => 'Each MAC address must be in a valid format (e.g., 5C:E9:1E:8F:8C:72, 5C-E9-1E-8F-8C-72, 5CE91E8F8C72).',

                /* With cisco style Mac address too */
                'MACAddressList.*.regex' => 'Each MAC address must be in a valid format (e.g., 5C:E9:1E:8F:8C:72, 5C-E9-1E-8F-8C-72, 5CE91E8F8C72,0001.1122.2333).',
            ]);
            $macAddresses = $validated['MACAddressList'];
            $MACInfo = $this->macLookupService->getRegisterForMultipleMac($macAddresses);
            if (!$MACInfo) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(
                (new RegisterMacResource($MACInfo))
            );
        } catch (Exception $e) {
            return $this->errorResponse([],
                $e->getMessage(),
                $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
