import {
    ACESFilmicToneMapping,
    BufferAttribute,
    CanvasTexture,
    CapsuleGeometry,
    CatmullRomCurve3,
    CircleGeometry,
    Color,
    CylinderGeometry,
    DirectionalLight,
    DoubleSide,
    Group,
    HemisphereLight,
    IcosahedronGeometry,
    MathUtils,
    Mesh,
    MeshBasicMaterial,
    MeshPhysicalMaterial,
    MeshStandardMaterial,
    PerspectiveCamera,
    PlaneGeometry,
    PMREMGenerator,
    RingGeometry,
    Scene,
    SphereGeometry,
    SRGBColorSpace,
    TorusGeometry,
    TubeGeometry,
    Vector3,
    WebGLRenderer,
} from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

/**
 * The assistant's 3D character: a small, friendly university host in a navy
 * suit with a ruby brooch, round glasses, swept-back hair, a mustache and a
 * goatee. Built from shaped primitives (no model file to fetch), lit with a
 * generated studio environment so the glasses, hair and gold catch light.
 *
 * One WebGL renderer for the page. Its canvas moves between the launcher and
 * the panel header (mount), which keeps the GL context instead of creating a
 * second one. The loop stops while the canvas is off screen or the tab is
 * hidden, and with reduced motion it only renders when the state changes.
 *
 * States, as the robot before it: idle (breathes, sways, blinks, looks at the
 * pointer), thinking (strokes his goatee, eyes up, sparks circle his head,
 * the stand glows amber), talking (mouth and eyebrows move, the stand glows
 * green), happy (squinting smile and a hop; falls back to idle). wave()
 * raises a hand.
 */

const BRAND_NAVY = new Color('#202759');
const STATE_GLOW = {
    idle: new Color('#7fe6ff'),
    thinking: new Color('#ffb547'),
    talking: new Color('#6ef2b0'),
    happy: new Color('#7fe6ff'),
};

/* Head: an ellipsoid with a softer, narrower jaw. Features sit on its surface. */
const HEAD = { rx: 0.62, ry: 0.58, rz: 0.56, y: 0.98 };

export function createCharacter({ reducedMotion = false } = {}) {
    const renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = SRGBColorSpace;
    renderer.toneMapping = ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;
    renderer.setClearColor(0x000000, 0);

    const canvas = renderer.domElement;
    canvas.className = 'spu-chatbot__canvas';
    canvas.setAttribute('aria-hidden', 'true');

    const scene = new Scene();
    const pmrem = new PMREMGenerator(renderer);
    const environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environment = environment;
    pmrem.dispose();

    const camera = new PerspectiveCamera(28, 1, 0.1, 50);
    camera.position.set(0, 0.62, 6.4);
    camera.lookAt(0, 0.56, 0);

    // Soft studio light, a cool blue rim on one side and a warm one on the
    // other (the evening-bokeh look of the portrait the character is drawn from).
    scene.add(new HemisphereLight(0xffffff, 0xc3cbe8, 0.9));
    const key = new DirectionalLight(0xfff4ea, 2.1);
    key.position.set(2.5, 4, 5);
    scene.add(key);
    const fill = new DirectionalLight(0xdfe7ff, 0.55);
    fill.position.set(-3, 0.5, 4);
    scene.add(fill);
    const rimCool = new DirectionalLight(0x6fa8ff, 2.2);
    rimCool.position.set(-4, 2.5, -3);
    scene.add(rimCool);
    const rimWarm = new DirectionalLight(0xffb36b, 1.4);
    rimWarm.position.set(4, 1.5, -3);
    scene.add(rimWarm);

    const parts = buildCharacter();
    scene.add(parts.root);

    /* ── State ─────────────────────────────────────────────────────────── */

    let state = 'idle';
    let stateSince = 0;
    let waveUntil = 0;
    let nextBlink = 1.5;
    let blinkEnd = 0;
    let nextGlint = 3;
    let headTarget = { x: 0, y: 0 };
    let eyeTarget = { x: 0, y: 0 };
    let container = null;
    let visible = true;
    let running = false;
    let frame = 0;
    let lastFrameTime = 0;
    const start = performance.now();
    let activeUntil = start + 4_000;
    const glow = STATE_GLOW.idle.clone();

    const resizeObserver = new ResizeObserver(() => resize());
    const intersection = new IntersectionObserver((entries) => {
        visible = entries.some((entry) => entry.isIntersecting);
        schedule();
    });
    document.addEventListener('visibilitychange', () => schedule());

    function resize() {
        if (!container) {
            return;
        }
        const width = Math.max(1, container.clientWidth);
        const height = Math.max(1, container.clientHeight);
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        // A tall stage shows the whole character; a small round avatar frames the head.
        const avatar = height < 80;
        camera.fov = avatar ? 18 : 28;
        camera.position.set(0, avatar ? 1.16 : 0.62, avatar ? 6.6 : 6.4);
        camera.lookAt(0, avatar ? 1.14 : 0.56, 0);
        camera.updateProjectionMatrix();
        render(performance.now());
    }

    function wake(durationMs = 3_500) {
        activeUntil = Math.max(activeUntil, performance.now() + durationMs);
        schedule();
    }

    function isAnimatedNow(now = performance.now()) {
        return state === 'thinking' || state === 'talking' || now < activeUntil;
    }

    function schedule() {
        const shouldRun = !reducedMotion && visible && !document.hidden && container !== null && isAnimatedNow();
        if (shouldRun && !running) {
            running = true;
            frame = requestAnimationFrame(loop);
        } else if (!shouldRun && running) {
            running = false;
            cancelAnimationFrame(frame);
        }
    }

    function loop(now) {
        if (!running) {
            return;
        }
        if (!isAnimatedNow(now)) {
            running = false;
            render(now);

            return;
        }
        frame = requestAnimationFrame(loop);
        // About 45 frames a second is smooth for a small character and
        // kinder to a laptop battery than 120.
        if (now - lastFrameTime < 21) {
            return;
        }
        lastFrameTime = now;
        render(now);
    }

    function render(now) {
        animate((now - start) / 1000);
        renderer.render(scene, camera);
    }

    function animate(time) {
        const sinceState = time - stateSince;
        const motion = reducedMotion ? 0 : 1;
        const thinking = state === 'thinking';
        const talking = state === 'talking';
        const happy = state === 'happy' && sinceState < 2.2;
        const ease = (from, to, amount) => MathUtils.lerp(from, to, reducedMotion ? 1 : amount);

        // Breathe, sway, and hop when happy.
        const hop = state === 'happy' && sinceState < 0.55 ? Math.sin((sinceState / 0.55) * Math.PI) * 0.16 * motion : 0;
        parts.figure.position.y = Math.sin(time * 1.5) * 0.025 * motion + hop;
        parts.figure.rotation.z = Math.sin(time * 0.8) * 0.02 * motion;
        parts.torso.scale.y = 1 + Math.sin(time * 1.5) * 0.012 * motion;
        parts.shadow.scale.setScalar(1 - parts.figure.position.y * 0.9);
        parts.shadow.material.opacity = 0.24 - parts.figure.position.y * 0.4;

        // Head follows the pointer; thinking tilts it and looks up.
        parts.head.rotation.y = ease(parts.head.rotation.y, headTarget.y + (thinking ? -0.12 : 0), 0.08);
        parts.head.rotation.x = ease(parts.head.rotation.x, headTarget.x + (thinking ? -0.12 : 0), 0.08);
        parts.head.rotation.z = ease(parts.head.rotation.z, (thinking ? 0.12 : 0) + Math.sin(time * 1.2) * 0.02 * motion, 0.06);

        // Eyes: pupils follow the pointer (up and aside while thinking).
        const lookX = thinking ? -0.5 : eyeTarget.x;
        const lookY = thinking ? 0.7 : eyeTarget.y;
        for (const eye of parts.eyes) {
            eye.iris.position.x = ease(eye.iris.position.x, eye.irisHome.x + lookX * 0.032, 0.2);
            eye.iris.position.y = ease(eye.iris.position.y, eye.irisHome.y + lookY * 0.028, 0.2);
        }

        // Blink (and squint when happy).
        if (time > nextBlink) {
            blinkEnd = time + 0.13;
            nextBlink = time + 2.2 + Math.random() * 3.2;
        }
        const blinking = time < blinkEnd && !reducedMotion;
        const eyeOpen = blinking ? 0.08 : happy ? 0.38 : 1;
        for (const eye of parts.eyes) {
            eye.group.scale.y = ease(eye.group.scale.y, eyeOpen, blinking ? 0.65 : 0.25);
        }

        // Eyebrows: up when happy or talking, one raised while thinking.
        const browLift = happy ? 0.035 : talking ? 0.012 + Math.abs(Math.sin(time * 5)) * 0.018 : 0;
        parts.brows[0].position.y = ease(parts.brows[0].position.y, browLift + (thinking ? 0.045 : 0), 0.2);
        parts.brows[1].position.y = ease(parts.brows[1].position.y, browLift - (thinking ? 0.01 : 0), 0.2);
        parts.brows[0].rotation.z = ease(parts.brows[0].rotation.z, thinking ? 0.12 : 0, 0.15);

        // Mouth: a smile; open and moving while talking, wider when happy.
        const openTarget = talking ? 0.25 + Math.abs(Math.sin(time * 13)) * 0.75 * (0.6 + 0.4 * Math.sin(time * 3.1)) : happy ? 0.55 : 0.0;
        parts.mouthOpen.scale.y = ease(parts.mouthOpen.scale.y, Math.max(0.001, openTarget), 0.4);
        parts.mouthOpen.visible = parts.mouthOpen.scale.y > 0.02;
        parts.smile.scale.x = ease(parts.smile.scale.x, happy ? 1.15 : 1, 0.2);
        parts.cheeks.forEach((cheek) => {
            cheek.material.opacity = ease(cheek.material.opacity, happy ? 0.42 : 0.24, 0.1);
        });

        // A glint sweeps across the glasses now and then.
        if (time > nextGlint) {
            nextGlint = time + 4 + Math.random() * 4;
        }
        const glintPhase = MathUtils.clamp(1 - (nextGlint - time) / 0.6, 0, 1);
        for (const glint of parts.glints) {
            glint.position.x = glint.userData.home - 0.14 + glintPhase * 0.28;
            glint.material.opacity = glintPhase > 0 && glintPhase < 1 ? Math.sin(glintPhase * Math.PI) * 0.75 * motion : 0;
        }

        // The brooch's ruby sparkles.
        parts.gem.material.emissiveIntensity = 0.35 + (Math.sin(time * 2.2) * 0.5 + 0.5) * 0.4 * (motion || 0.5);
        parts.gem.rotation.z = Math.sin(time * 0.7) * 0.15 * motion;

        // The stand's light takes the state's color.
        glow.lerp(STATE_GLOW[state] ?? STATE_GLOW.idle, reducedMotion ? 1 : 0.08);
        const pulse = 0.7 + (Math.sin(time * (thinking ? 6 : 2.2)) * 0.5 + 0.5) * 0.6 * (motion || 0.5);
        parts.standRing.material.color.copy(glow);
        parts.standRing.material.opacity = 0.55 + pulse * 0.3;
        parts.standGlow.material.color.copy(glow);
        parts.standGlow.material.opacity = 0.12 + pulse * 0.08;

        // Thinking sparks circle the head.
        parts.sparks.visible = thinking;
        if (thinking) {
            parts.sparks.rotation.y = time * 2.4;
            parts.sparks.children.forEach((spark, index) => {
                spark.position.y = Math.sin(time * 3 + index * 2.1) * 0.06;
                spark.scale.setScalar(0.8 + Math.sin(time * 6 + index) * 0.25);
            });
        }

        // Arms: rest with a gentle swing, wave, or a hand on the chin while thinking.
        const waving = time < waveUntil;
        const swing = Math.sin(time * 1.6) * 0.05 * motion;
        const left = parts.leftArm;
        const right = parts.rightArm;
        left.shoulder.rotation.x = ease(left.shoulder.rotation.x, thinking ? -1.55 : 0, 0.1);
        left.shoulder.rotation.z = ease(left.shoulder.rotation.z, thinking ? -0.68 : 0.14 + swing, 0.1);
        left.elbow.rotation.x = ease(left.elbow.rotation.x, thinking ? -1.5 : -0.12, 0.1);
        left.elbow.rotation.z = ease(left.elbow.rotation.z, thinking ? -0.35 + Math.sin(time * 3) * 0.06 * motion : 0, 0.1);
        right.shoulder.rotation.x = ease(right.shoulder.rotation.x, 0, 0.1);
        right.shoulder.rotation.z = ease(right.shoulder.rotation.z, waving ? -2.55 : -0.14 - swing, waving ? 0.22 : 0.1);
        right.elbow.rotation.x = ease(right.elbow.rotation.x, -0.12, 0.1);
        right.elbow.rotation.z = ease(right.elbow.rotation.z, waving ? -0.35 + Math.sin(time * 12) * 0.45 : 0, waving ? 0.3 : 0.1);

        if (state === 'happy' && sinceState > 2.2) {
            state = 'idle';
        }
    }

    /* ── API ───────────────────────────────────────────────────────────── */

    return {
        mount(target) {
            if (!target || target === container) {
                return;
            }
            if (container) {
                resizeObserver.unobserve(container);
                intersection.unobserve(container);
            }
            container = target;
            container.append(canvas);
            resizeObserver.observe(container);
            intersection.observe(container);
            resize();
            wake(4_000);
        },
        setState(next) {
            if (!STATE_GLOW[next] || next === state) {
                return;
            }
            state = next;
            stateSince = (performance.now() - start) / 1000;
            if (reducedMotion) {
                render(performance.now());
            } else {
                wake(4_000);
            }
        },
        wave() {
            waveUntil = (performance.now() - start) / 1000 + 1.5;
            wake(2_600);
        },
        lookAt(clientX, clientY) {
            if (!container || reducedMotion) {
                return;
            }
            const rect = container.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 3;
            const x = MathUtils.clamp((clientX - cx) / (window.innerWidth / 2), -1, 1);
            const y = MathUtils.clamp((clientY - cy) / (window.innerHeight / 2), -1, 1);
            headTarget = { y: x * 0.45, x: y * 0.25 };
            eyeTarget = { x, y: -y };
            wake(2_000);
        },
        dispose() {
            running = false;
            cancelAnimationFrame(frame);
            resizeObserver.disconnect();
            intersection.disconnect();
            environment.dispose();
            renderer.dispose();
            canvas.remove();
        },
    };
}

/* ── Shape helpers ───────────────────────────────────────────────────────── */

function smoothstep(edge0, edge1, x) {
    const t = MathUtils.clamp((x - edge0) / (edge1 - edge0), 0, 1);

    return t * t * (3 - 2 * t);
}

/** Round cheeks narrowing to the chin: x scale at height y (head-local). */
function jaw(y) {
    return 1 - 0.24 * smoothstep(0.05, 1, -y / HEAD.ry) ** 1.4;
}

/** The point on the face at (x, y), lifted `out` along the view axis. */
function onFace(x, y, out = 0) {
    const rx = HEAD.rx * jaw(y);
    const inside = 1 - (x / rx) ** 2 - (y / HEAD.ry) ** 2;

    return new Vector3(x, y, HEAD.rz * Math.sqrt(Math.max(0.0001, inside)) + chin(x, y) + out);
}

/** A soft chin the goatee sits on. */
function chin(x, y) {
    return 0.035 * Math.exp(-((x / 0.16) ** 2) - (((y + 0.44) / 0.12) ** 2));
}

/** A tube along points whose radius follows radiusAt(t), t from 0 to 1. */
function taperedTube(points, radiusAt, tubular = 40, radial = 10) {
    const curve = new CatmullRomCurve3(points);
    const geometry = new TubeGeometry(curve, tubular, 1, radial, false);
    const position = geometry.attributes.position;
    const center = new Vector3();
    const vertex = new Vector3();
    for (let i = 0; i <= tubular; i++) {
        curve.getPointAt(i / tubular, center);
        const radius = Math.max(0.0006, radiusAt(i / tubular));
        for (let j = 0; j <= radial; j++) {
            const index = i * (radial + 1) + j;
            vertex.fromBufferAttribute(position, index).sub(center).multiplyScalar(radius).add(center);
            position.setXYZ(index, vertex.x, vertex.y, vertex.z);
        }
    }
    geometry.computeVertexNormals();

    return geometry;
}

/** The face points along a curve, for features that follow the face. */
function facePath(points, out, side = 1) {
    return points.map(([x, y]) => onFace(side * x, y, out));
}

/** The head's skin: an ellipsoid with round cheeks, a narrower jaw and a chin. */
function headGeometry() {
    const geometry = new SphereGeometry(1, 72, 54);
    const position = geometry.attributes.position;
    const vertex = new Vector3();
    for (let i = 0; i < position.count; i++) {
        vertex.fromBufferAttribute(position, i);
        const y = vertex.y * HEAD.ry;
        const x = vertex.x * HEAD.rx * jaw(y);
        const z = vertex.z * HEAD.rz;
        position.setXYZ(i, x, y, z + (z > 0 ? chin(x, y) : 0));
    }
    geometry.computeVertexNormals();

    return geometry;
}

/**
 * Hair: the head's shape pushed outward above a hairline (high at the
 * forehead, at the ears on the sides, low at the nape) and pushed inside
 * the head below it, so no cut edges show. Above the forehead it rises into
 * a quiff swept up, back and over to one side, with a parting on his right,
 * short faded sides (a lighter tone) and a few soft combed waves.
 */
function hairGeometry() {
    const geometry = new SphereGeometry(1, 128, 96);
    const position = geometry.attributes.position;
    const colors = new Float32Array(position.count * 3);
    const dark = new Color('#15100d');
    const faded = new Color('#3b322d');
    const color = new Color();
    const vertex = new Vector3();
    for (let i = 0; i < position.count; i++) {
        vertex.fromBufferAttribute(position, i);
        const azimuth = Math.atan2(vertex.x, vertex.z);
        const front = Math.cos(azimuth);
        const side = Math.abs(Math.sin(azimuth));
        const hairline = front > 0
            ? MathUtils.lerp(0.2, 0.7, smoothstep(0, 1, front) ** 1.6)
            : MathUtils.lerp(0.2, -0.48, (-front) ** 0.9);
        const covered = smoothstep(hairline - 0.02, hairline + 0.03, vertex.y);
        const rise = smoothstep(hairline, hairline + 0.32, vertex.y);
        const towardFront = smoothstep(-0.4, 0.95, front);
        const sweep = 0.82 + 0.3 * smoothstep(-1, 1, Math.sin(azimuth + 0.5));
        const quiff = 0.17 * rise * towardFront * sweep;
        const crown = 0.03 * smoothstep(0.0, 0.85, vertex.y);
        const top = smoothstep(0.45, 0.75, vertex.y);
        const sides = MathUtils.lerp(0.012, 0.035, top);
        const parting = -0.03 * Math.exp(-(((azimuth + 0.62) / 0.045) ** 2)) * rise;
        const waves = 0.006 * Math.sin(azimuth * 11 + vertex.y * 5) * rise * towardFront;
        const radius = MathUtils.lerp(0.94, 1.02 + sides + quiff + crown + parting + waves, covered);

        const y = vertex.y * HEAD.ry;
        position.setXYZ(
            i,
            vertex.x * HEAD.rx * jaw(y) * radius,
            y * radius + quiff * 0.16,
            vertex.z * HEAD.rz * radius + quiff * 0.08 * Math.max(0, front),
        );

        color.copy(faded).lerp(dark, Math.max(top, 1 - side * 0.9));
        colors.set([color.r, color.g, color.b], i * 3);
    }
    geometry.setAttribute('color', new BufferAttribute(colors, 3));
    geometry.computeVertexNormals();

    return geometry;
}

/**
 * The quiff's locks: thick tapered strands that start at the hairline, roll
 * up and forward over the forehead, then sweep back over the crown, each a
 * little further round towards his left so the whole quiff leans one way.
 */
function hairLocks(material) {
    const group = new Group();
    const locks = [
        // [start azimuth, sweep, front lift, thickness]
        [-0.66, 0.16, 0.17, 0.15],
        [-0.45, 0.19, 0.23, 0.17],
        [-0.22, 0.22, 0.28, 0.18],
        [0.02, 0.24, 0.3, 0.18],
        [0.26, 0.22, 0.27, 0.17],
        [0.5, 0.18, 0.2, 0.15],
        [0.74, 0.13, 0.12, 0.11],
    ];
    for (const [azimuth, sweep, lift, thickness] of locks) {
        const points = [];
        const steps = 9;
        for (let index = 0; index <= steps; index++) {
            const t = index / steps;
            // From the front hairline (40° above level) over the top to the back (150°).
            const angle = MathUtils.degToRad(MathUtils.lerp(38, 150, t));
            const az = azimuth + sweep * smoothstep(0, 0.6, t);
            const roll = lift * Math.exp(-(((t - 0.16) / 0.15) ** 2)) + 0.05 * (1 - t);
            const radius = 0.97 + roll;
            const horizontal = Math.cos(angle);
            const vertical = Math.sin(angle);
            points.push(new Vector3(
                Math.sin(az) * horizontal * HEAD.rx * radius,
                vertical * HEAD.ry * radius + 0.02,
                Math.cos(az) * horizontal * HEAD.rz * radius,
            ));
        }
        const geometry = taperedTube(points, (t) => thickness * (0.55 + 0.45 * Math.exp(-(((t - 0.18) / 0.22) ** 2))) * (1 - 0.6 * t), 48, 16);
        group.add(new Mesh(geometry, material));
    }

    return group;
}

/**
 * The jacket is a capsule scaled to an elliptic section; its front surface
 * at (x, y) in torso space.
 */
const TORSO = { radius: 0.36, half: 0.1, rx: 0.38, rz: 0.31, y: -0.02 };

function onTorsoZ(x, y = 0) {
    const overshoot = Math.max(0, Math.abs(y) - TORSO.half);
    const section = Math.sqrt(Math.max(0.0001, TORSO.radius ** 2 - overshoot ** 2)) / TORSO.radius;
    const inside = 1 - (x / (TORSO.rx * section)) ** 2;

    return TORSO.rz * section * Math.sqrt(Math.max(0.0001, inside));
}

/**
 * The front of the suit, painted: the shirt's open V and collar, the
 * lapels with their notches and stitched edges, two buttons and the breast
 * pocket. Drawn once on a canvas and laid on a panel that follows the
 * jacket's curve, so the edges stay crisp at any size.
 */
const PANEL = { width: 0.76, height: 0.92 };

function suitFrontTexture() {
    const width = 512;
    const height = Math.round(width * (PANEL.height / PANEL.width));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    // Torso units to canvas pixels (x from -0.4, y from the top at +0.48).
    const px = (x) => ((x + PANEL.width / 2) / PANEL.width) * width;
    const py = (y) => ((PANEL.height / 2 - y) / PANEL.height) * height;
    const polygon = (points) => {
        context.beginPath();
        points.forEach(([x, y], index) => (index === 0 ? context.moveTo(px(x), py(y)) : context.lineTo(px(x), py(y))));
        context.closePath();
    };

    // Shirt: the open V with a soft shadow down the middle.
    polygon([[-0.15, 0.5], [0.15, 0.5], [0.0, 0.02]]);
    const shirt = context.createLinearGradient(px(-0.15), 0, px(0.15), 0);
    shirt.addColorStop(0, '#e9ebf2');
    shirt.addColorStop(0.5, '#ffffff');
    shirt.addColorStop(1, '#e4e7ef');
    context.fillStyle = shirt;
    context.fill();
    // Placket buttons on the shirt.
    context.fillStyle = '#d5d9e4';
    for (const y of [0.3, 0.18]) {
        context.beginPath();
        context.arc(px(0), py(y), 4, 0, Math.PI * 2);
        context.fill();
    }

    for (const side of [-1, 1]) {
        // Collar point, with its shadow on the shirt.
        polygon([[side * 0.015, 0.5], [side * 0.16, 0.5], [side * 0.085, 0.34]]);
        context.fillStyle = 'rgba(20, 28, 70, 0.18)';
        context.save();
        context.translate(side * 3, 5);
        context.fill();
        context.restore();
        polygon([[side * 0.015, 0.5], [side * 0.16, 0.5], [side * 0.085, 0.34]]);
        context.fillStyle = '#fbfbfd';
        context.fill();
        context.strokeStyle = '#cfd4e2';
        context.lineWidth = 2;
        context.stroke();

        // Lapel along the V, with its notch.
        const lapel = [[side * 0.16, 0.5], [side * 0.29, 0.44], [side * 0.26, 0.38], [side * 0.3, 0.33], [side * 0.03, -0.04], [side * 0.0, 0.0]];
        polygon(lapel);
        const fill = context.createLinearGradient(px(side * 0.02), 0, px(side * 0.3), 0);
        fill.addColorStop(0, '#16204c');
        fill.addColorStop(1, '#22306a');
        context.fillStyle = fill;
        context.fill();
        // Shadow the lapel casts on the jacket, and a pick-stitch highlight.
        context.strokeStyle = 'rgba(8, 12, 36, 0.55)';
        context.lineWidth = 5;
        context.beginPath();
        context.moveTo(px(side * 0.3), py(0.33));
        context.lineTo(px(side * 0.03), py(-0.04));
        context.stroke();
        context.strokeStyle = 'rgba(160, 180, 255, 0.35)';
        context.lineWidth = 1.5;
        context.setLineDash([3, 4]);
        context.beginPath();
        context.moveTo(px(side * 0.275), py(0.335));
        context.lineTo(px(side * 0.045), py(-0.01));
        context.stroke();
        context.setLineDash([]);
    }

    // Buttons.
    for (const y of [-0.12, -0.27]) {
        context.beginPath();
        context.arc(px(0), py(y), 9, 0, Math.PI * 2);
        context.fillStyle = '#121a40';
        context.fill();
        context.beginPath();
        context.arc(px(-0.004), py(y + 0.006), 4, 0, Math.PI * 2);
        context.fillStyle = 'rgba(160, 180, 255, 0.35)';
        context.fill();
    }

    // Breast pocket welt (his left, the viewer's right) and the jacket's
    // centre line below the buttons.
    context.strokeStyle = 'rgba(8, 12, 36, 0.55)';
    context.lineWidth = 4;
    context.beginPath();
    context.moveTo(px(0.2), py(-0.06));
    context.lineTo(px(0.33), py(-0.045));
    context.stroke();
    context.lineWidth = 3;
    context.beginPath();
    context.moveTo(px(0), py(-0.33));
    context.lineTo(px(0.02), py(-0.48));
    context.stroke();

    const texture = new CanvasTexture(canvas);
    texture.colorSpace = SRGBColorSpace;
    texture.anisotropy = 4;

    return texture;
}

function suitFrontGeometry() {
    const geometry = new PlaneGeometry(PANEL.width, PANEL.height, 40, 48);
    const position = geometry.attributes.position;
    for (let i = 0; i < position.count; i++) {
        position.setZ(i, onTorsoZ(position.getX(i), position.getY(i)) + 0.003);
    }
    geometry.computeVertexNormals();

    return geometry;
}

/* ── The character ───────────────────────────────────────────────────────── */

function buildCharacter() {
    const skin = new MeshPhysicalMaterial({ color: 0xe6a77e, roughness: 0.55, sheen: 0.35, sheenColor: new Color('#ff9e7d'), sheenRoughness: 0.5 });
    const skinShade = new MeshPhysicalMaterial({ color: 0xd8946d, roughness: 0.6, sheen: 0.3, sheenColor: new Color('#ff8f70') });
    const hair = new MeshPhysicalMaterial({ color: 0xffffff, vertexColors: true, roughness: 0.32, clearcoat: 0.6, clearcoatRoughness: 0.28, sheen: 0.3, sheenColor: new Color('#5d5070') });
    const beard = new MeshStandardMaterial({ color: 0x18110e, roughness: 0.7 });
    const suit = new MeshPhysicalMaterial({ color: 0x1c2654, roughness: 0.62, sheen: 0.35, sheenColor: new Color('#4c60b4'), sheenRoughness: 0.45 });
    const shirt = new MeshPhysicalMaterial({ color: 0xf8f8fb, roughness: 0.5 });
    const gold = new MeshStandardMaterial({ color: 0xe2b864, roughness: 0.2, metalness: 0.95 });
    const frame = new MeshStandardMaterial({ color: 0x8a909c, roughness: 0.18, metalness: 1 });
    const shoe = new MeshPhysicalMaterial({ color: 0x15161c, roughness: 0.22, clearcoat: 1, clearcoatRoughness: 0.12 });
    const lip = new MeshStandardMaterial({ color: 0xa8483a, roughness: 0.42 });

    const root = new Group();
    const figure = new Group();
    root.add(figure);

    /* Legs and shoes: short, as a cartoon's are. */
    for (const side of [-1, 1]) {
        const leg = new Mesh(new CapsuleGeometry(0.1, 0.1, 6, 16), suit);
        leg.position.set(side * 0.13, -0.56, 0);
        figure.add(leg);
        const foot = new Mesh(new SphereGeometry(1, 28, 18), shoe);
        foot.scale.set(0.13, 0.072, 0.19);
        foot.position.set(side * 0.14, -0.72, 0.06);
        figure.add(foot);
    }

    /* Torso: the jacket, its painted front, and the brooch. */
    const torso = new Group();
    torso.position.y = TORSO.y;
    figure.add(torso);

    const jacket = new Mesh(new CapsuleGeometry(TORSO.radius, TORSO.half * 2, 10, 36), suit);
    jacket.scale.set(TORSO.rx / TORSO.radius, 1, TORSO.rz / TORSO.radius);
    torso.add(jacket);

    const front = new Mesh(suitFrontGeometry(), new MeshPhysicalMaterial({ map: suitFrontTexture(), transparent: true, roughness: 0.55, sheen: 0.3, sheenColor: new Color('#8798e0'), depthWrite: false }));
    torso.add(front);

    // A raised collar behind the neck.
    const collarBack = new Mesh(new TorusGeometry(0.15, 0.035, 10, 32, Math.PI * 1.25), shirt);
    collarBack.rotation.x = Math.PI / 2;
    collarBack.rotation.z = -Math.PI * 0.125 + Math.PI;
    collarBack.position.set(0, 0.47, -0.02);
    torso.add(collarBack);

    // The brooch on his left lapel (viewer's right): a gold frame of beads
    // around a ruby, and a gold chain draping to the breast pocket.
    const broochAt = new Vector3(0.2, 0.2, 0);
    const brooch = new Group();
    brooch.position.set(broochAt.x, broochAt.y, onTorsoZ(broochAt.x, broochAt.y) + 0.026);
    brooch.rotation.y = Math.asin(broochAt.x / TORSO.rx) * 0.9;
    torso.add(brooch);
    brooch.add(new Mesh(new TorusGeometry(0.04, 0.01, 10, 36), gold));
    for (let index = 0; index < 12; index++) {
        const angle = (index / 12) * Math.PI * 2;
        const bead = new Mesh(new SphereGeometry(0.0095, 10, 8), gold);
        bead.position.set(Math.cos(angle) * 0.054, Math.sin(angle) * 0.054, -0.004);
        brooch.add(bead);
    }
    const gem = new Mesh(
        new IcosahedronGeometry(0.032, 1),
        new MeshPhysicalMaterial({ color: 0xd3102e, roughness: 0.04, clearcoat: 1, clearcoatRoughness: 0.02, ior: 1.77, emissive: new Color('#6b0012'), emissiveIntensity: 0.4, flatShading: true }),
    );
    gem.scale.set(0.85, 1.15, 0.6);
    gem.position.z = 0.012;
    brooch.add(gem);

    const pocket = new Vector3(0.31, -0.05, 0);
    const chainStart = new Vector3(broochAt.x, broochAt.y - 0.055, onTorsoZ(broochAt.x, broochAt.y - 0.055) + 0.022);
    const chainEnd = new Vector3(pocket.x, pocket.y, onTorsoZ(pocket.x, pocket.y) + 0.012);
    for (const sag of [0.06, 0.12]) {
        const mid = new Vector3(0.25 + sag * 0.15, 0.05 - sag, 0);
        mid.z = onTorsoZ(mid.x, mid.y) + 0.02;
        torso.add(new Mesh(taperedTube([chainStart, mid, chainEnd], () => 0.005, 32, 6), gold));
    }
    const pin = new Mesh(new SphereGeometry(0.012, 12, 10), gold);
    pin.position.copy(chainEnd);
    torso.add(pin);

    /* Arms: shoulder and elbow joints so he can wave and think. */
    const makeArm = (side) => {
        const shoulder = new Group();
        shoulder.position.set(side * 0.355, 0.32, 0);
        const upper = new Mesh(new CapsuleGeometry(0.088, 0.12, 6, 18), suit);
        upper.position.y = -0.11;
        shoulder.add(upper);
        const elbow = new Group();
        elbow.position.y = -0.22;
        shoulder.add(elbow);
        const fore = new Mesh(new CapsuleGeometry(0.082, 0.1, 6, 18), suit);
        fore.position.y = -0.08;
        elbow.add(fore);
        const cuff = new Mesh(new CylinderGeometry(0.079, 0.079, 0.035, 20), shirt);
        cuff.position.y = -0.19;
        elbow.add(cuff);
        const hand = new Mesh(new SphereGeometry(0.085, 24, 18), skin);
        hand.scale.set(1, 1.08, 0.92);
        hand.position.y = -0.27;
        elbow.add(hand);
        const thumb = new Mesh(new CapsuleGeometry(0.028, 0.035, 4, 10), skin);
        thumb.position.set(-side * 0.055, -0.24, 0.05);
        thumb.rotation.z = side * 0.6;
        elbow.add(thumb);
        figure.add(shoulder);

        return { shoulder, elbow };
    };
    const leftArm = makeArm(1);
    const rightArm = makeArm(-1);

    /* Head. */
    const neck = new Mesh(new CylinderGeometry(0.14, 0.16, 0.18, 24), skinShade);
    neck.position.y = 0.48;
    figure.add(neck);

    const head = new Group();
    head.position.y = HEAD.y;
    figure.add(head);
    head.add(new Mesh(headGeometry(), skin));
    head.add(new Mesh(hairGeometry(), hair));
    head.add(hairLocks(new MeshPhysicalMaterial({ color: 0x15100d, roughness: 0.3, clearcoat: 0.7, clearcoatRoughness: 0.25, sheen: 0.3, sheenColor: new Color('#5d5070') })));

    for (const side of [-1, 1]) {
        const ear = new Mesh(new SphereGeometry(1, 20, 16), skin);
        ear.scale.set(0.065, 0.12, 0.085);
        ear.position.set(side * HEAD.rx * 0.97, -0.02, -0.03);
        head.add(ear);
        const inner = new Mesh(new SphereGeometry(1, 16, 12), skinShade);
        inner.scale.set(0.03, 0.075, 0.05);
        inner.position.set(side * HEAD.rx * 1.01, -0.02, -0.01);
        head.add(inner);
    }

    // Nose.
    const nose = new Mesh(new SphereGeometry(1, 24, 18), skin);
    nose.scale.set(0.072, 0.062, 0.068);
    nose.position.copy(onFace(0, -0.095, -0.025));
    head.add(nose);

    // Cheeks.
    const cheeks = [-1, 1].map((side) => {
        const cheek = new Mesh(new CircleGeometry(0.08, 24), new MeshBasicMaterial({ color: 0xff8c74, transparent: true, opacity: 0.24, depthWrite: false }));
        cheek.position.copy(onFace(side * 0.35, -0.12, 0.006));
        cheek.lookAt(cheek.position.clone().multiplyScalar(2));
        head.add(cheek);

        return cheek;
    });

    // Eyes: big and brown, with two catchlights each.
    const eyes = [-1, 1].map((side) => {
        const group = new Group();
        group.position.copy(onFace(side * 0.22, 0.02, -0.028));
        head.add(group);
        const sclera = new Mesh(new SphereGeometry(0.128, 32, 24), new MeshPhysicalMaterial({ color: 0xfbfbfa, roughness: 0.18, clearcoat: 1 }));
        sclera.scale.set(1, 1.12, 0.7);
        group.add(sclera);
        const iris = new Group();
        const irisHome = new Vector3(0, -0.006, 0.08);
        iris.position.copy(irisHome);
        group.add(iris);
        const irisDisc = new Mesh(new SphereGeometry(0.082, 28, 20), new MeshPhysicalMaterial({ color: 0x6a3a1a, roughness: 0.28, clearcoat: 1 }));
        irisDisc.scale.z = 0.3;
        iris.add(irisDisc);
        const ring = new Mesh(new TorusGeometry(0.078, 0.008, 8, 32), new MeshBasicMaterial({ color: 0x3b1f0d }));
        ring.position.z = 0.012;
        iris.add(ring);
        const pupil = new Mesh(new SphereGeometry(0.043, 20, 14), new MeshBasicMaterial({ color: 0x120a06 }));
        pupil.scale.z = 0.3;
        pupil.position.z = 0.014;
        iris.add(pupil);
        const highlight = new Mesh(new SphereGeometry(0.021, 12, 10), new MeshBasicMaterial({ color: 0xffffff }));
        highlight.position.set(0.028, 0.032, 0.028);
        iris.add(highlight);
        const small = new Mesh(new SphereGeometry(0.01, 10, 8), new MeshBasicMaterial({ color: 0xffffff }));
        small.position.set(-0.027, -0.026, 0.028);
        iris.add(small);
        // Upper lid line, so a blink reads as a closed eye.
        const lid = new Mesh(new TorusGeometry(0.125, 0.008, 8, 28, Math.PI), beard);
        lid.scale.set(1, 1.12, 1);
        lid.position.z = 0.045;
        group.add(lid);

        return { group, iris, irisHome };
    });

    // Eyebrows: thick and dark, arched, thicker at the inner end.
    const brows = [-1, 1].map((side) => {
        const pivot = new Group();
        head.add(pivot);
        const brow = new Mesh(taperedTube(facePath([[0.085, 0.255], [0.2, 0.305], [0.33, 0.265]], 0.012, side), (t) => 0.033 - t * 0.017, 28, 10), beard);
        pivot.add(brow);

        return pivot;
    });

    // Glasses: thin round metal rims, a bridge, temples to the ears, faint
    // lenses, and a glint that sweeps across now and then.
    const glasses = new Group();
    head.add(glasses);
    const glints = [];
    const rimZ = Math.max(onFace(0.22, 0.02).z + 0.06, 0.6);
    for (const side of [-1, 1]) {
        const center = new Vector3(side * 0.22, 0.02, rimZ);
        const rim = new Mesh(new TorusGeometry(0.168, 0.0085, 10, 56), frame);
        rim.position.copy(center);
        rim.rotation.y = side * 0.14;
        glasses.add(rim);
        const lens = new Mesh(new CircleGeometry(0.166, 44), new MeshPhysicalMaterial({ color: 0xeaf3ff, transparent: true, opacity: 0.12, roughness: 0.02, metalness: 0.1, depthWrite: false }));
        lens.position.copy(center);
        lens.rotation.y = side * 0.14;
        glasses.add(lens);
        const glint = new Mesh(new CapsuleGeometry(0.013, 0.17, 4, 8), new MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0, depthWrite: false }));
        glint.rotation.z = -0.6;
        glint.position.copy(center).add(new Vector3(0, 0, 0.004));
        glint.userData.home = center.x;
        glasses.add(glint);
        glints.push(glint);
        glasses.add(new Mesh(taperedTube([
            new Vector3(side * 0.385, 0.05, rimZ - 0.04),
            new Vector3(side * 0.56, 0.05, 0.28),
            new Vector3(side * HEAD.rx * 0.99, 0.03, -0.02),
        ], () => 0.008, 20, 6), frame));
    }
    glasses.add(new Mesh(taperedTube([
        new Vector3(-0.055, 0.06, rimZ),
        new Vector3(0, 0.085, rimZ + 0.012),
        new Vector3(0.055, 0.06, rimZ),
    ], () => 0.008, 16, 6), frame));

    // Mustache: two neat strokes from under the nose, tips curling up.
    for (const side of [-1, 1]) {
        head.add(new Mesh(taperedTube(facePath([[0.018, -0.165], [0.085, -0.188], [0.165, -0.196], [0.235, -0.175], [0.27, -0.138]], 0.012, side), (t) => 0.032 * Math.sin(Math.min(1, 0.35 + t * 1.3) * Math.PI * 0.5 + 0.25) * (1 - t * 0.78), 36, 12), beard));
    }

    // Smile, and the open mouth behind it when talking.
    const smile = new Mesh(taperedTube(facePath([[-0.16, -0.24], [-0.08, -0.278], [0, -0.29], [0.08, -0.278], [0.16, -0.24]], 0.004), (t) => 0.006 + 0.012 * Math.sin(t * Math.PI), 32, 8), lip);
    head.add(smile);
    const mouthOpen = new Mesh(new SphereGeometry(1, 24, 16), new MeshStandardMaterial({ color: 0x4f1a16, roughness: 0.6 }));
    mouthOpen.geometry.translate(0, -0.5, 0);
    mouthOpen.scale.set(0.1, 0.001, 0.03);
    mouthOpen.position.copy(onFace(0, -0.283, -0.012));
    head.add(mouthOpen);

    // Goatee: a soul patch running down from under the lip into a short
    // beard that follows the chin.
    head.add(new Mesh(taperedTube(facePath([[0, -0.322], [0, -0.37], [0, -0.415]], 0.006), (t) => 0.02 + 0.012 * t, 16, 10), beard));
    head.add(new Mesh(taperedTube(facePath([[-0.11, -0.41], [-0.055, -0.445], [0, -0.458], [0.055, -0.445], [0.11, -0.41]], 0.006), (t) => 0.012 + 0.03 * Math.sin(t * Math.PI), 32, 12), beard));

    /* Thinking sparks around the head. */
    const sparks = new Group();
    sparks.position.y = HEAD.y + 0.5;
    sparks.visible = false;
    for (let index = 0; index < 3; index++) {
        const angle = (index / 3) * Math.PI * 2;
        const spark = new Mesh(new SphereGeometry(0.035, 12, 10), new MeshBasicMaterial({ color: 0xffd27a }));
        const holder = new Group();
        holder.position.set(Math.cos(angle) * 0.75, 0, Math.sin(angle) * 0.75);
        holder.add(spark);
        sparks.add(holder);
    }
    figure.add(sparks);

    /* The stand: a glowing ring under his feet and its soft light, then the shadow. */
    const standY = -0.79;
    const standRing = new Mesh(new TorusGeometry(0.44, 0.016, 10, 64), new MeshBasicMaterial({ color: STATE_GLOW.idle, transparent: true, opacity: 0.8 }));
    standRing.rotation.x = Math.PI / 2;
    standRing.position.y = standY;
    root.add(standRing);
    const standGlow = new Mesh(new RingGeometry(0.1, 0.62, 48), new MeshBasicMaterial({ color: STATE_GLOW.idle, transparent: true, opacity: 0.16, depthWrite: false, side: DoubleSide }));
    standGlow.rotation.x = -Math.PI / 2;
    standGlow.position.y = standY - 0.005;
    root.add(standGlow);
    const shadow = new Mesh(new CircleGeometry(0.36, 40), new MeshBasicMaterial({ color: BRAND_NAVY, transparent: true, opacity: 0.24, depthWrite: false }));
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.y = standY + 0.005;
    root.add(shadow);

    return { root, figure, torso, head, eyes, brows, cheeks, glints, smile, mouthOpen, gem, sparks, standRing, standGlow, shadow, leftArm, rightArm };
}
